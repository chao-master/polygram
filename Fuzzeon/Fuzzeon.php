<?php

error_reporting(E_ALL);

include_once "Tg.php";
include_once "monsters.php";

class Fuzzeon extends Tg {
    const BOT_ID        = "163722860";
    const BOT_USERNAME  = "fuzzeonBot";

    protected static $PRIVATE_VALS = [
        "dungeon" => 0,
        "distance" => 0,
        "unspentSkill" => 0,
        "player.health" => 10,
        "player.indurance" => 1,
        "player.attack" => 1,
        "player.defence" => 1,
        "player.speed" => 1,
        "monster.name" => "",
        "monster.health" => 0,
        "monster.indurance" => 0,
        "monster.attack" => 0,
        "monster.defence" => 0,
        "monster.speed" => 0
    ];
    protected static $GROUP_VALS = [];
    protected static $GROUP_PRIVATE_VALS = [];

    function level($n){
        if (is_string($n)){
            $n = $this->userVals[$n];
        }
        return floor((sqrt(1+8*$n)-1)/2);
    }
    function roll($n){
        if(!is_int($n)){
            $n = $this->level($n);
        }
        if ($n == 0){
            return rand(1,3) == 6 ? 3 : 1;
        } else {
            return rand($n,$n*6);
        }
    }

    function generateMonster(){
        $monster = \monsters\makeMonster($this->userVals["distance"]);
        $this->userVals["monster.name"] = $monster["name"];

        $this->userVals["monster.indurance"] = $monster["stats"][0];
        $this->userVals["monster.attack"] = $monster["stats"][1];
        $this->userVals["monster.defence"] = $monster["stats"][2];
        $this->userVals["monster.speed"] = $monster["stats"][3];
        $this->userVals["monster.health"] = max(5,10*$this->level("monster.indurance"));
    }

    function resetGame(){
        //Modified so the player dosen't lose everything anymore.
        $this->userVals["distance"] = 0;
        $this->userVals["player.health"] = $this->level("player.indurance")*10;
        $this->userVals["monster.health"] = 0; //Setting monster health to 0 lets us advance to the next room correctlly.
    }

    function on_firstRun(){
        $this->sendMessage("Welcome brave adventurer to the dangerous of the Fuzzeon Dungeon filled with the most dangerous monster and most fablous loot!
                            At the end of it's 100 rooms awaits the dungeon's unclaimed treasure guarded by the boss monster.");

    }

    /**
    * Check how far through the dungeon you are and how much health is left on the room's monster.
    */
    function command_progress(){
        $dist = $this->userVals["distance"];
        $msg = "";
        if ($dist == 0){
            $msg .= "You are currentlly standing outside the dungeon, wondering if you should go in.";
        } else {
            if ($dist == 101){
                $msg .= "You are currentlly standing inside the boss' chambers.\n";
            } else {
                $msg .= "You are currentlly in room $dist/100.\n";
            }
            $monsterHealth = $this->userVals["monster.health"];
            $monsterName = $this->userVals["monster.name"];
            if ($monsterHealth <= 0){
                $msg .= "The $monsterName that guarded this room is defeated";
            } else {
                $msg .= "The $monsterName in it has $monsterHealth/".max(5,$this->level("monster.indurance")*10)." health.";
            }
        }
        $this->sendMessage($msg);
    }

    /**
    * Proceed to the next room of the dunegon.
    */
    function command_advance(){
        if ($this->userVals["monster.health"] > 0){
            $this->sendMessage("You cannot proceed while the monster lives");
        } else {
            $dist = $this->userVals["distance"];
            $msg = "You enter the ";
            if ($dist == 0){
                $msg .= "first room ";
            } elseif ($dist == 100){
                $msg .= "boss' chambers ";
            } elseif ($dist == 101){
                $msg .= "treasure room ";
            } else {
                $msg .= "next room ";
            }
            $this->userVals["distance"] ++;
            $msg .= "of the dungeon.\nInside you find a ";
            $this->generateMonster();
            $msg .= $this->userVals["monster.name"] . " [";
            $msg .= $this->level("monster.indurance")."/";
            $msg .= $this->level("monster.attack")."/";
            $msg .= $this->level("monster.defence")."/";
            $msg .= $this->level("monster.speed")."]";

            $this->userVals["_last_"] = "command_";
            $this->sendMessage($msg,null,null,[
                "keyboard"=>[["attack"]]
            ]);
        }
    }

    /**
    * Attack the room's monster with a basic attack.
    * The damage done is dependent apon your own attack and the monster's defence
    */
    function command_attack(){
        if ($this->userVals["distance"] == 0){
            $this->sendMessage("You are outside the dungeon, try going inside to attack monsters.");
        } elseif ($this->userVals["monster.health"] <= 0){
            $this->sendMessage("You have already slain the monster so there is nothing left to fight.");
        } else {
            //Build up the levels and monster info
            $levels = [
                "indurance" => $this->level("monster.indurance"),
                "attack" => $this->level("monster.attack"),
                "defence" => $this->level("monster.defence"),
                "speed" => $this->level("monster.speed")
            ];
            $monsterName = $this->userVals["monster.name"];
            $monster = \monsters\MONSTERS()[$monsterName];

            $msg = "";
            $movePirority = 0;
            //Setup
            if(SETUP & $monster[2]){
                $fun='\monsters\\'.$monster[1].'_pre';
                $resp=$fun($levels,$movePirority,$this->userVals);
                if($resp){
                    $msg .= $resp;
                }
            }
            //Speed Check
            $playerSpeed = $this->roll("player.speed");
            $monsterSpeed = $this->roll($levels["speed"]);
            if(SPEED & $monster[2]){
                $fun='\monsters\\'.$monster[1].'_speed';
                $resp=$fun($playerSpeed,$monsterSpeed,$levels,$vals);
                if($resp){
                    $msg .= $resp;
                }
            }
            if($movePirority == 0){
                $playersAttack = $playerSpeed > $monsterSpeed;
            } else {
                $playersAttack = $movePirority > 0;
            }
            //Game attack order
            for($turn=0;$turn<2;$turn++){
                if($playersAttack){
                    //Player's Attack
                    $playerAtk = $this->roll("player.attack");
                    $monsterDef = $this->roll($levels["defence"]);
                    if(DEFENCE & $monster[2]){
                        $fun='\monsters\\'.$monster[1].'_def';
                        $resp=$fun($playerAtk,$monsterDef,$levels,$this->userVals);
                        if($resp){
                            $msg .= $resp;
                        }
                    }
                    $dmg = max(0,$playerAtk-$monsterDef);
                    $msg .= "You deal $dmg to $monsterName.\n";
                    $this->userVals["monster.health"] -= $dmg;
                } else {
                    //Monsters's Attack
                    $playerDef = $this->roll("player.defence");
                    $monsterAtk = $this->roll($levels["attack"]);
                    if(ATTACK & $monster[2]){
                        $fun='\monsters\\'.$monster[1].'_atk';
                        $resp=$fun($playerDef,$monsterAtk,$levels,$this->userVals);
                        if($resp){
                            $msg .= $resp;
                        }
                    }
                    $dmg = max(0,$monsterAtk-$playerDef);
                    $msg .= "The $monsterName deals $dmg damage to you.\n";
                    $this->userVals["player.health"] -= $dmg;
                }

                if($this->userVals["player.health"] <= 0){
                    $msg .= "You fall to the ground dead.";
                    $this->resetGame();
                    continue;
                } elseif ($this->userVals["monster.health"] <= 0){
                    $msg .= "The $monsterName falls to the ground dead.\nYou gain 1 skill point";
                    $this->userVals["unspentSkill"] ++;
                    continue;
                }
                //Swap attack
                $playersAttack = !$playersAttack;
            }

            $this->sendMessage($msg);
        }
    }

    /**
    * Attack until one of you is dead.
    * Intended for debug use only - calls command_attack untill the monster is no longer there.
    */
    function command_attackForever(){
        while($this->userVals["monster.health"] > 0){
            $this->command_attack();
        }
    }

    function levelProgress($n){
        $n = $this->userVals[$n];
        $level = $this->level($n);
        $start = ($level)*($level+1)/2;
        $goal = $level+1;
        $at = $n-$start;
        return "$level [$at/$goal]";
    }

    /**
    * Check your stats and levels.
    * Shows your current and max health along with your levels, how many skill points you have in each and how many you need for the next level.
    * Also shows your unspent skill points.
    */
    function command_stats(){
        $msg = "Player Stats:\n";
        $msg .= "Health: " . $this->userVals["player.health"] . "/" . $this->level("player.indurance")*10 ."\n";
        $msg .= "Indurance: " . $this->levelProgress("player.indurance") . "\n";
        $msg .= "Attack: " . $this->levelProgress("player.attack") . "\n";
        $msg .= "Defence: " . $this->levelProgress("player.defence") . "\n";
        $msg .= "Speed: " . $this->levelProgress("player.speed") . "\n";
        $msg .= "Unspent Skill points: " . $this->userVals["unspentSkill"];
        $this->sendMessage($msg);
    }

    /**
    * [skill] Spends enough Skill points to level a skill if you can.
    */
    function command_level($skill){
        if ($skill != "attack" && $skill != "defence" && $skill != "speed" && $skill != "indurance"){
            $this->sendMessage("$skill is not a valid skill. Valid skills are: attack, defence, speed and indurance");
            return;
        }
        $exp = $this->userVals["player.$skill"];
        $level = $this->level($exp);
        $next = ($level+2)*($level+1)/2;
        $need = $next-$exp;
        $unspent = $this->userVals["unspentSkill"];
        if($need > $unspent){
            $this->sendMessage("You need $need more experince to level $skill however you only have $unspent unspent skill points");
        } else {
            $this->userVals["player.$skill"] += $need;
            $this->userVals["unspentSkill"] -= $need;
            $level++;
            if ($skill == "indurance"){
                $this->userVals["player.health"] += 10;
            }
            $this->sendMessage("You spend the $need skill points needed to raise $skill to $level.");
        }
    }
}

?>
