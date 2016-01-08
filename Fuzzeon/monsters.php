<?php namespace monsters;
    define("SETUP", 1);
    define("SPEED", 2);
    define("ATTACK", 4);
    define("DEFENCE", 8);

    function MONSTERS(){
        return [
            //Name, [Indurance/Atk/Def/Spd Shares],
            "juggernaught test construct" => [[0.4,0.2,0.3,0.1], null, 0],
            "mystic test construct" =>       [[0.3,0.4,0.1,0.2], null, 0],
            "iron test construct" =>         [[0.3,0.2,0.4,0.1], null, 0],
            "charging test construct" =>     [[0.2,0.3,0.1,0.4], null, 0],
            "snapping test construct" =>     [[0.1,0.4,0.2,0.3], null, 0],
            "nimble test construct" =>       [[0.1,0.3,0.2,0.4], null, 0],
            "leaping test construct" =>      [[0.1,0.2,0.3,0.4], null, 0],
            "fragile test construct" =>      [[0.2,0.4,0.1,0.3], null,0 ],

            "Lumbering Iron Guard" =>        [[0.5,0.1,0.5,0],  "ironGuard",5]
        ];
    }

    function makeMonster($level){
        $name = array_rand(MONSTERS());
        $monster = MONSTERS()[$name];
        $stats = [];
        foreach($monster[0] as $val){
            $sl = level($level*$val);
            $ml = rand(floor($level*$sl*0.9),ceil($level*$sl*1.1));
            $ml = max($ml,1);
            $stats[] = $ml*($ml+1)/2;
        }
        return [
            "name" => $name,
            "stats" => $stats
        ];
    }

    function level($n){
        return (sqrt(1+8*$n)-1)/2;
    }


    /** Iron guard - always attacks second but gains atk if it completly blocks */
    function ironGuard_pre(&$levels,&$movePirority,$vals){
        $movePirority = 5;
    }
    function ironGuard_def(&$playerRoll,&$monsterRoll,&$levels,$vals){
        if ($monsterRoll > $playerRoll){
            $boost = ($monsterRoll-$playerRoll)/2;
            $levels["attack"] += $boost;
            return "The guard's thick armour damages you as you attack";
        }
    }

?>
