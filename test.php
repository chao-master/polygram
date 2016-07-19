<?php

    function testDetails($bot,$cls){
        print "Checking bot details match:\n";
        $bUser = $bot->getMe();
        print "        ID " . ($bUser["id"] == $cls::BOT_ID ? "Match" : "Mis-Match ") . "\n";
        print "  username " . ($bUser["username"] == $cls::BOT_USERNAME ? "Match" : "Mis-Match ") . "\n";
        print "\n";
        return ($bUser["id"] == $cls::BOT_ID) && ($bUser["username"] == $cls::BOT_USERNAME);
    }

    function testWebhook($bot,$cls){
        print "Checking to see if a webhook is established:\n";
        $rCode = $bot->sendPackage("getUpdates",["limit"=>1,"timeout"=>0],true);
        if (!is_int($rCode)){
            print "  Fail: No webhook is setup\n";
            $pass = false;
        } else if ($rCode == 409){
            print "  Pass: A webhook exists\n";
            $pass = true;
        } else {
            print "  Error: $rCode while checking\n";
            $pass = false;
        }
        print "\n";
        return $pass;
    }

    function checkDatabase($bot,$cls){
        print "Checking Database\n";
        $db = new SQLite3("$cls.db");
        if($cls::$PRIVATE_VALS){
            $tablesquery = $db->query("PRAGMA table_info(private)");
            $table = $tablesquery->fetchArray(SQLITE3_ASSOC);
            if (!$table){
                print "  Fail: Private table not found\n";
            } else {
                print_r($table);
            }
        }
        print "\n";
    }

    function setupWebhook($bot,$cls,$address){
        if ($address == "-"){
            print "Removing webhook\n";
            $url = "";
        } else {
            $url = "$address/hook.php?class=$cls&key=".$cls::getKey();
            print "Setting up webhook to point to $url\n";
        }
        $rCode = $bot->sendPackage("setWebhook",["url"=>$url]);
        if (is_int($rCode)){
            print "  Error $rCode when setting up webhook\n";
        } else {
            print "  Webhook setup\n";
        }
        print "\n";
    }

    //Main
    if (php_sapi_name() !== 'cli') {
        die("Only enabled from cli");
    }

    if ($argc < 1) {
        die("Bot name needed for first argument");
    }

    $className=$argv[1];

    set_include_path(get_include_path() . PATH_SEPARATOR . getcwd());
    chdir($className);


    print "Loading bot $className";
    include "$className.php";

    $bot = new $className();
    testDetails($bot,$className);
    if (!testWebhook($bot,$className)){
        if ($argc >= 3 && $argv[2]){
            setupWebhook($bot,$className,$argv[2]);
        } else {
            print "rerun with the path of the webhook base to add";
        }
    }

    //checkDatabase($bot,$className);

?>
