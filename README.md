# Minion, run our daily standup.

Make minion run daily standups. 

## Installation
Clone this into a directory named `Standup` in your minion's `plugins` subdirectory. 

## Configuration
Put something like the following in your minion's `config.php`.

    $this->PluginConfig['Standup'] = array(
            'Time' => '10:00',
            'Channels' => array(
                '#minion.php'
            ),
            'SkipDays' => array(
                'Saturday',
                'Sunday'
            ),
            'SkipUsers' => array(
                'minion'
            ),
            'UserWait' => 90,
            'StandupWait' => 150,
            'MaxRetry' => 2
    );

## What it does
* Starts Standup at a specific time on every day not in `SkipDays` with an announcement.
* Grabs a list of all users in the channel and calls on random users to do standup. If a user quits or joins the server, the list is updated accordingly.
* If a user says 'B:' or 'Blocks:' or similar, ending their standup, minion will choose a new person from the list.
* If `StandupWait` seconds elapses without anyone responding, minion will cancel standup.
* If `UserWait` seconds elapses without _username_ responding, minion will add them to the end of the list and continue.
* If Minion has tried calling a user more than `MaxRetry`, it will remove that user from standup
* Minion recognizes both usernames and nicknames.
* If a user is skipped twice, they will not be called again.

## Usage
Standup will run automatically at the specified `Time`. There are some commands to modify this behavior:
* `!standup` forces minion to start standup
* `!sitdown` forces minion to end standup
* skip [username] will skip a user and move on to the next user in the list
* remove [username] will remove a user from the standup list, they will not be called again.
