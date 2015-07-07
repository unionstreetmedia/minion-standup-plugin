<?php

namespace Minion\Plugins;

date_default_timezone_set ('America/New_York');

/** 
 * Minion, run our daily standup
 */
class Standup extends \Minion\Plugin {
    
        
    /**
     * removeUser()
     * Removes a specified username from the current users array of a specified channel
     *
     * @param string $name The name of the user to be removed
     * @param string $channel The name of the channel the user is being removed from
     *
     * @return void
     */
    public function removeUser($name, $channel) {
        if ($this->currentUsers[$channel] && $name) {
            foreach ($this->currentUsers[$channel] as $key => $user) {
                if ($user[0] == $name) {
                    array_splice($this->currentUsers[$channel], $key, 1);
                } 
            }
        }
    }
    
    
    /**
     * sitDown()
     * Ends standup and resets any pertinent variables
     *
     * @return void
     */
    public function sitDown() {
        $this->forceStandup = false;
        $this->standing = false;
        
        $this->currentUsers = array();
        $this->currentUSer = array();
        
        $this->callFirst = true;
        $this->hasResponded = false;
        
        $this->now = array();
        $this->startTime = array();
        
        $this->populated = false;
        $this->whoisCounter = array();
    }
    
    
    /**
     * pickUser()
     * Picks a user from the top of the array of current users. 
     * If there are no users to pick or the same user is picked twice in a row, standup ends.
     * Different messages are displayed for the first user picked and subsequent users.
     *
     * @return void
     */
    public function pickUser($channel) {
        // Checks to make sure we still have users to pick
        if ($this->populated) {
        
            // Grab the first user in the array
            $user = array_shift($this->currentUsers[$channel]);
            
            // Check if we've called the first user yet
            if ($this->callFirst) {
                // Check for repeat user calls
                if ($user[0] == $this->currentUser[$channel][0]) {
                    $this->Minion->msg("That's all folks, have a good day!", $channel);
                    $this->sitDown();
                } else {
                    $this->Minion->msg($user[0] . " first!", $channel);
                    $this->callFirst = false;
                    
                    $this->currentUser[$channel] = $user;
                    $this->startTime[$channel] = time();
                }
            
            // We have already called the first user, so go ahead and call someone else
            } else {
                // Check for repeat user calls
                if ($user[0] == $this->currentUser[$channel][0]) {
                    $this->Minion->msg("That's all folks, have a good day!", $channel);
                    $this->sitDown();
                } else {
                    $this->Minion->msg("Go " . $user[0] . "!", $channel);
                    $this->currentUser[$channel] = $user;
                }
            }

            // We just called someone, so start a timer to make sure they respond
            $this->now[$channel] = time();
            
        // There are no more users left to pick, so we end standup.
        } else {
            $this->Minion->msg("That's all folks, have a good day!", $channel);
            $this->sitDown();
        }
    }

    
    /**
     * cleanUsers()
     * Cleans up the current uses array once it is filled.
     */
    public function cleanUsers($channel) {
        
        // Randomize the order of the users in the array, so there's a different order
        //  every standup
        shuffle($this->currentUsers[$channel]);
    
        // Some users should be skipped during standup, so remove them right now
        if ($this->skipUsers) {
            foreach ($this->skipUsers as $user) {
                $this->removeUser($user, $channel);
            }
        }
    
        // Remove Minion from standup so he doesn't call on himself
        $this->removeUser($this->Minion->State['Nickname'], $channel);

        // Call the first user
        if ($this->callFirst) {
            $this->pickUser($channel);   
        }
    }
}


$Standup = new Standup(
    'Standup',
    'Minion, run our daily standup.',
    'Paul D\'Amora'
);

return $Standup


/**
 * This event is triggered as soon as we run minion.
 * It initiates all of the $Standup variables and gets config values. 
 */
->on('before-loop', function () use ($Standup) {
    $Standup->forceStandup = true;
    $Standup->standing = false;
    
    $Standup->channels = $Standup->conf('Channels');
    $Standup->currentUsers = array();
    $Standup->currentUser = array();
    
    $Standup->callFirst = true;
    $Standup->hasResponded = false;
    
    $Standup->time = $Standup->conf('Time');
    $Standup->skipDays = $Standup->conf('SkipDays');
    $Standup->skipUsers = $Standup->conf('SkipUsers');
    
    $Standup->userWait = $Standup->conf('UserWait');
    $Standup->standupWait = $Standup->conf('StandupWait');
    
    $Standup->now = array(); 
    $Standup->startTime = array();
    
    $Standup->maxRetry = $Standup->conf('MaxRetry');
    
    $Standup->populated = false;
    $Standup->whoisCounter = array();
})


/**
 * This event is triggered at the end of the loop.
 * It starts standup on the specified days and the specified time.
 */
->on('loop-end', function () use ($Standup) {
    $time = date('H:i');
    $today = date('l');
    
    // Check if it's time for standup to start
    if ((($time == date('H:i', strtotime($Standup->time)) && !in_array($today, $Standup->skipDays)) || $Standup->forceStandup) && !$Standup->standing) { 
        $Standup->standing = true;
        
        foreach($Standup->channels as $channel) {
            $Standup->whoisCounter[$channel] = 0;
            $Standup->Minion->msg("Good morning! Let's do standup.", $channel);
            $Standup->Minion->cmd("NAMES", "{$channel}");
        }
    }
})


/**
 * This event is triggered at the start of the loop.
 * It continuously checks the current time against response timers.
 * If there hasn't been a response in x amount of time,
 * _username_ is moved to the end of the list or
 * standup is ended.
 */
->on('loop-start', function () use ($Standup) {
    // Check if there's been any responses for this standup
    if ($Standup->standing && !$Standup->hasResponded) {
        foreach($Standup->channels as $channel) {
            
            // If there hasn't been any responses and time is past the wait time, we end standup
            if (isset($Standup->startTime[$channel]) && time() > $Standup->startTime[$channel] + $Standup->standupWait) {
                $Standup->Minion->msg(
                    'No response in over ' . $Standup->standupWait . ' seconds. Standup is cancelled for today. Type !standup to restart it.', 
                    $channel
                );
                $Standup->sitDown();
            }
        }
    }

    // Check if the first user has been called
    if ($Standup->standing && !$Standup->callFirst) {
        foreach($Standup->channels as $channel) {
            
            // If we've already called someone, but they haven't responded past the wait time,
            // We move that person to the end of the list and choose someone else
            if (isset($Standup->now[$channel]) && time() > $Standup->now[$channel] + $Standup->userWait && $Standup->currentUser[$channel]) {
                if ($Standup->currentUser[$channel][2] < $Standup->maxRetry) {
                    var_dump($Standup->currentUser[$channel]);
                    $Standup->currentUser[$channel][2]++;
                    array_push($Standup->currentUsers[$channel], $Standup->currentUser[$channel]);
                    $Standup->Minion->msg($Standup->currentUser[$channel][0] . ' took too long and has been moved to the end of the list.', $channel);
                }
                
                $Standup->pickUser($channel);
            }
        }
    } 
    
})


/**
 * This event is triggered when a user types a message into IRC.
 * Minion reads the parsed message and determine if it's a command that
 *  he should care about.
 *
 * If the message matches a regex, Minion take the appropriate action.
 */
->on('PRIVMSG', function (&$data) use ($Standup) {
    $channel = $data['arguments'][0];
    
    // Get any commands.
    list ($command, $arguments) = $Standup->simpleCommand($data);
    
    // If the command is !standup, force start standup
    if ($command == 'standup') {
        $Standup->sitDown();
        $Standup->forceStandup = true;
    
    // If the command is !sitdown, force standup to end
    } else if ($command == 'sitdown') {
        $Standup->sitDown();
        $this->Minion->msg("That's all folks, have a good day!", $channel);
    }
    
    // Check if standup is running on this channel
    if (in_array($channel, $Standup->channels) && $Standup->standing) {
        
        // Match any message that ends a person's standup,
        // i.e. B:, Blocks:, Blocking:, Blocked:, (case insensitive)
        $pattern = '/B+(?:lock(?:s|ing|ed)?)?:/i';
        $matches = $Standup->matchCommand($data, $pattern);
        
        // Match any message that indicates a user is busy
        // i.e. _username_ is out, isn't here, is not, is in a meeting (case insensitive)
        $pattern2 = "/({$Standup->currentUser[$channel][0]}|{$Standup->currentUser[$channel][1]})+(?:is|s|'s(?:out|n't|nt|not|in|))?/i";
        $matches2 = $Standup->matchCommand($data, $pattern2);
        
        // Check if this is the first response for this standup
        if (!$Standup->hasResponded) {
            $Standup->hasResponded = true;    
        }
        
        // Check if there are matches for someone ending their standup
        // If so, pick someone else to go
        if ($matches) {
            $Standup->pickUser($channel);
        
        // Check if there are matches for someone being busy
        // If so, update that user's "retries", add them to the end of the list, and pick a new person
        } else if ($matches2) {
            if ($Standup->currentUser[$channel][1] < $Standup->maxRetry) {
                array_push($Standup->currentUsers[$channel], $Standup->currentUser[$channel]);
                $Standup->currentUser[$channel][1]++;
                $Standup->Minion->msg("{$Standup->currentUser[$channel][0]} is unavailable. Moved to the end of the list.", $channel);
            }
            $Standup->pickUser($channel);
        }
    }

})


/**
 * This event is triggered when the NAMES command is used
 * It creates an array of users currently in the channel where standup was started,
 */
->on('353', function (&$data) use ($Standup) {

    // No point in running this if standup isn't even happening
    if ($Standup->standing && $data['message']) {
        $channel = $data['arguments'][2];
        $Standup->currentUsers[$channel] = explode(" ", $data['message']);
        
        // Add a retry counter attached to every current user
        // Get every user's real name and attach it to them
        foreach ($Standup->currentUsers[$channel] as $user) {
            $Standup->Minion->cmd("WHOIS", "{$user}");
        }       
    }
})


/**
 * This event is triggered by the WHOIS command. 
 * Minion uses it to get users real names.
 */
->on('311', function (&$data) use ($Standup) {
    
    $nickname = $data['arguments'][1];
    
    
    $realName = explode(' ', $data['message'])[0];
    
    // Attach a real name and retry counter to every user 
    foreach ($Standup->channels as $channel) {
        foreach ($Standup->currentUsers[$channel] as $key => $user) {
            if ($user == $nickname) {
                $Standup->currentUsers[$channel][$key] = array($nickname, $realName, 0);
            } 
        }
        
        $Standup->whoisCounter[$channel]++;
    
        // Check to see if we're ready to 
        if ($Standup->whoisCounter[$channel] == count($Standup->currentUsers[$channel])) {
            $Standup->populated = true;
            $Standup->cleanUsers($channel);
        }
    }
})


/** 
 * This event is triggered when someone joins the channel
 * Minion adds the newbie to the standup list if standup is currently happening
 */
->on('JOIN', function (&$data) use ($Standup) {
    $channel = $data['message'];
    $newUser = $data['source'];
    
    // Use a regex to get the actual name of the newbie
    preg_match('/(.*)(?=!)/', $newUser, $matches);
    
    if ($matches[0] != $Standup->Minion->State['Nickname'] && $Standup->standing) {
        array_push($Standup->currentUsers[$channel], array($matches[0], $matches[0], 0));
    }   
})


/** 
 * This event is triggered when someone quits the server (different from PART)
 * Minion removes the quitter from the standup list if standup is currently happening
 */
->on('QUIT', function (&$data) use ($Standup) {
    $oldUser = $data['source'];
    
    // Use a regex to get the actual name of the quitter
    preg_match('/(.*)(?=!)/', $newUser, $matches);
    
    if ($Standup->standing) {
        foreach ($Standup->channels as $channel) { 
           $Standup->currentUsers[$channel] = $Standup->removeUser($matches[0], $Standup->currentUsers[$channel]);
        }
    }
})

?>
