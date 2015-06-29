# Minion, run our daily standup.

Make minion run daily standups. 

## Installation
Clone this into a directory named `Standup` in your minion's `plugins` subdirectory. In the `Standup` directory, run

    $ composer install

to install the necessary library.

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
            'StandupWait' => 150
    );

## What it does
* Starts Standup at a specific time on every day not in `SkipDays` with an announcement.
* Grabs a list of all users in the channel and calls on random users to do standup. If a user quits or joins the server, the list is updated accordingly.
* If a user says 'B:' or 'Blocks:' or similar, ending their standup, minion will choose a new person from the list.
* If username is supposed to be doing their standup and another user says "username isn't here" or similar, minion immediately moves them to the end of the list and continues.
* If `StandupWait` seconds elapses without anyone responding, minion will cancel standup.
* If `UserWait` seconds elapses without _username_ responding, minion will add them to the end of the list and continue.


## Usage
Standup will run automatically at the specified `Time`. In order to force standup to start at an abnormal time, use the command: `!standup`.