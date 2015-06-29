<?php

namespace Minion\Plugins;

class Standup extends \Minion\Plugin {

    public function removeUser($name, $array) {
        if ($array) {
            $key = array_search($name, $array);
            array_splice($array, $key, 1);
            
            return $array;
        } 
        return false;
    }
    
    public function sitDown() {
        $this->forceStandup = false;
        $this->standing = false;
        
        $this->currentUsers = array();
        $this->currentUSer = array();
        
        $this->callFirst = true;
        $this->hasResponded = false;
        
        $this->now = array();
    }
    
    public function pickUser($channel) {
        $user = array_shift($this->currentUsers[$channel]);
        if ($user) {
            $this->Minion->msg("Go " . $user . "!", $channel);
            $this->currentUser[$channel] = $user;
        }
        
        // start the timer
        $this->now[$channel] = time();
    }
}

$Standup = new Standup(
    'Standup',
    'Minion, run our daily standup.',
    'Paul D\'Amora'
);

return $Standup

->on('before-loop', function () use ($Standup) {
    $Standup->forceStandup = true;
    $Standup->standing = false;
    
    $Standup->channels = $Standup->conf('Channels');
    $Standup->currentUsers = array();
    $Standup->currentUser = array();
    
    $Standup->callFirst = true; // call the first person
    $Standup->hasResponded = false; // first person has responded
    
    $Standup->time = $Standup->conf('Time');
    $Standup->skipDays = $Standup->conf('SkipDays');
    $Standup->skipUsers = $Standup->conf('SkipUsers');
    
    $Standup->userWait = $Standup->conf('UserWait');
    $Standup->standupWait = $Standup->conf('StandupWait');
    
    $Standup->now = array(); 
})

->on('loop-end', function () use ($Standup) {
    $time = date('H:i');
    $today = date('l');
    
    // check if it's time for standup
    if (($now == date('H:i', strtotime($time)) && !in_array($today, $Standup->skipDays)) || $Standup->forceStandup) { 
        if (!$Standup->standing) {
            // standup isn't happening, let's start it
            foreach($Standup->channels as $channel) {
                $Standup->Minion->msg("Good morning! Let's do standup.", $channel);
                
                // create list of users for standup
                $Standup->Minion->cmd("NAMES", "{$channel}");
                $Standup->standing = true;
            }
        } else if ($Standup->standing && $Standup->currentUsers && $Standup->callFirst) {
            // standup has started, we have a list of users, and we haven't called the first person yet
            foreach($Standup->channels as $channel) {
                if ($Standup->currentUsers[$channel]) {
                    $user = array_shift($Standup->currentUsers[$channel]);
                    
                    // call the first user
                    if ($user) {
                        $Standup->Minion->msg($user . " first!", $channel);
                        $Standup->callFirst = false;
                        
                        $Standup->currentUser[$channel] = $user;
                        // start the timer
                        $Standup->now[$channel] = time();
                    }
                }
            }
        }
    }
})

// this contains all of the time related statements
->on('loop-start', function () use ($Standup) {
    // user wait
    if ($Standup->standing && !$Standup->first) {
        foreach($Standup->channels as $channel) {
            if (isset($Standup->now[$channel]) && time() - $Standup->now[$channel] >= $Standup->userWait && $Standup->currentUser[$channel]) {
                // taking too long
                $Standup->Minion->msg($Standup->currentUser[$channel] . ' took too long and has been moved to the end of the list.', $channel);
                
                array_push($Standup->currentUsers[$channel], $Standup->currentUser[$channel]);
                $Standup->pickUser($channel);
            }
        }
    // standup wait
    } else if ($Standup->standing && !$Standup->hasResponded) {
        foreach($Standup->channels as $channel) {
            if (isset($Standup->now[$channel]) && time() - $Standup->now[$channel] >= $Standup->standupWait) {
                // taking too long
                $Standup->Minion->msg('No response in over ' . $Standup->standupWait . ' seconds. Standup is cancelled for today. Type "!standup" to restart it.', $channel);
                $Standup->sitDown();
            }
        }
    }
})

->on('PRIVMSG', function (&$data) use ($Standup) {
    // get current channel
    $currentChannel = $data['arguments'][0];
    
    // get commands 
    list ($command, $arguments) = $Standup->simpleCommand($data);
    
    // standup was forced to start
    if ($command == 'standup') {
        $Standup->sitDown(); // reset everything
        $Standup->forceStandup = true;
    }
    
    // check if standup is happening on this channel
    if (in_array($currentChannel, $Standup->channels) && $Standup->standing) {
        // watch for someone finished with blocks
        $pattern = '/B+(?:lock(?:s|ing|ed)?)?:/i';
        $matches = $Standup->matchCommand($data, $pattern);
        
        // watch for someone who isn't here
        $pattern2 = "/{$Standup->currentUser[$currentChannel]}+(?:is|s|'s(?:out|n't|nt|not|in|)?)?/i";
        $matches2 = $Standup->matchCommand($data, $pattern2);
        
        // we got our first response
        if (!$Standup->hasResponded) {
            $Standup->hasResponded = true;    
        }
        
        // if someone ended their standup
        if ($matches) {
            if ($Standup->currentUsers[$currentChannel]) {
                $Standup->pickUser($currentChannel);
            } else {
                // standup is finished
                $Standup->Minion->msg("That's all folks, have a good day!", $currentChannel);
                $Standup->sitDown();
            }
        } else if ($matches2) {
            $Standup->Minion->msg("{$Standup->currentUser[$currentChannel]} is unavailable. Moved to the end of the list.", $currentChannel);
            
            array_push($Standup->currentUsers[$currentChannel], $Standup->currentUser[$currentChannel]);
            $Standup->pickUser($currentChannel);
        }
    }

})

// 353 is the NAMES command
->on('353', function (&$data) use ($Standup) {
    var_dump($data);
    $currentChannel = $data['arguments'][2];
    $channelUsers = $Standup->currentUsers[$currentChannel];
    $channelUsers = explode(" ", $data['message']);
    
    // remove skipped users
    foreach ($Standup->skipUsers as $user) {
        $channelUsers = $Standup->removeUser($user, $channelUsers);
    }
    
    // remove minion
    $channelUsers = $Standup->removeUser($Standup->Minion->State['Nickname'], $channelUsers);
    
    // shuffle the array
    shuffle($channelUsers);
    
    $Standup->currentUsers[$currentChannel] = $channelUsers;
})

->on('JOIN', function (&$data) use ($Standup) {
    $currentChannel = $data['message'];
    $newUser = $data['source'];
    
    // get their name
    preg_match('/(.*)(?=!)/', $newUser, $matches);
    
    if ($matches[0] != $Standup->Minion->State['Nickname'] && $Standup->standing) {
        array_push($Standup->currentUsers[$currentChannel], $matches[0]);
    }   
})

->on('QUIT', function (&$data) use ($Standup) {
    $oldUser = $data['source'];
    
    // get their name
    preg_match('/(.*)(?=!)/', $newUser, $matches);
    
    if ($Standup->standing) {
        // remove the user from every currentUsers list
        foreach ($Standup->channels as $channel) { 
           $Standup->currentUsers[$channel] = $Standup->removeUser($matches[0], $Standup->currentUsers[$channel]);
        }
    }
})

?>
