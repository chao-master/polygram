<?php
abstract class Tg{
    const BASE_URI = "https://api.telegram.org/bot";
    const FILE_BASE_URI = "https://api.telegram.org/file/bot";

    const REPLY_CLEAR_KEYBOARD_ALL = 2;
    const REPLY_CLEAR_KEYBOARD_SELECTIVE = 3;
    const REPLY_FORCE_ALL = 4;
    const REPLY_FORCE_SELECTIVE = 5;
    const FILE_DOWNLOAD = 1;

    static protected $METHOD_TYPES = [
        "audio","document","sticker","video","voice","contact","location","venue","photo"
    ];

    static protected $BOT_KEY;

    static protected $PRIVATE_VALS = [];

    protected $chatId = null;
    protected $chatType = null;
    protected $userId = null;
    protected $messageId = null;
    protected $queryId = null;
    protected $userVals = [];

    function __construct(){
        static::getKey();
    }

    /**
    * Returns the bot key for checking
    */
    public static function getKey(){
        if (!isset(static::$BOT_KEY)){
            static::$BOT_KEY = trim(file_get_contents("authkey"));
        }
        return static::$BOT_KEY;
    }

    /**
    * Alerts the user that sent us the message to an error that has happened.
    */
    protected function userError($msg){
        $this->sendMessage($msg,true,$this->userId,null,null,null,true);
    }

    /**
    * Logs an error to the error file and sends a user friendly version to the user.
    */
    protected function systemError($msg,$userMessage="Sorry an unexpected error occoured"){
        error_log(get_class($this)." > $msg\n");
        $this->userError($userMessage);
    }

    /**
    * Logs a message to the log file.
    */
    protected function infoLog($msg){
        file_put_contents("info.log",print_r($msg,1)."\n",FILE_APPEND);
    }

    /**
    * Saves the persitant userVals
    */
    abstract protected function savePersistant();

    /**
    * Loads the persitant userVals, should call on_firstRun if none exist and return the defaults
    */
    abstract protected function loadPersistant();

    /**
    * Handles input from the webhook.
    * Calls either handleMessage or handleInlineQuery depending on the type of request
    */
    public function handleInput($input){
        if (isset($input["message"])){
            return $this->handleMessage($input["message"]);
        } else {
            return $this->handleInlineQuery($input["inline_query"]);
        }
    }

    /**
    * Checks the incomming message and delegates it to the apporiate command_* function
    * Correctlly identifies /command@bot - filtering out commands for other BOT_USERNAME
    * The paramaters passed to the command_* function are made up of the extra text in the message
    * the text is split by spaces upto the number of paramaters,
    * because of this no paramater (except the last) can contain a space
    * TODO - make it call on_text for basic text messages
    * ~~TODO - make it call registered handlers if a reply to a previous message~~
    * TODO - corretlly handle non-text messages
    */
    private function handleMessage($message){
        $id         = $message["message_id"];
        $from       = $message["from"];
        $chat       = $message["chat"];
        $timeStamp  = $message["date"];
        $text       = $message["text"];
        $cmdName    = null;

        $this->userId = $from["id"];
        $this->chatId = $chat["id"];
        $this->messageId = $id;
        $this->chatType = $message["chat"]["type"];

        $this->loadPersistant();

        if (!$text){
            //Not a text command
            return $this->handleNonTextMessage($message);
        } elseif (substr($text,0,1) == "/"){
            //We have a command
            $cmdParams = explode(" ",$text);
            $cmdNameP = explode("@",substr(array_shift($cmdParams),1),2);
            if (count($cmdNameP) == 1 || $cmdNameP[1] == static::BOT_USERNAME) {
                //This command is for us
                $cmdName = "command_".$cmdNameP[0];
            } else {
                //Command is not for us.
                return false;
            }
        } else {
            if ($this->userVals["_last_"]) {
                //It's a reply to something
                $text = $this->userVals["_last_"].$text;
                $cmdParams = explode(" ",$text);
                $cmdNameP = [array_shift($cmdParams)];
                $cmdName = $cmdNameP[0];
            } else {
                return;
                //TODO If not we set a method to handle basic text.
            }
        }
        //Get function and arguments ready
        try {
            $funcInfo = new ReflectionMethod($this,$cmdName);
        } catch (Exception $e){
            $this->userError("/$cmdNameP[0] is not a valid command.");
            return;
        }
        $pCount = $funcInfo->getNumberOfParameters();
        if ($pCount === 0){
            $cmdParams = [];
        } else {
            $lastParamA = array_splice($cmdParams,$pCount-1,count($cmdParams));
            $lastParam = implode(" ",$lastParamA);
            array_push($cmdParams,$lastParam);
        }
        //Check we have enough paramaters
        $pCountNeed = $funcInfo->getNumberOfRequiredParameters();
        $pCountGiven = count($cmdParams);
        if($pCountGiven < $pCountNeed){
            $this->userError("/$cmdName requires $pCountNeed paramaters but only $pCountGiven where given.");
        } else {
            call_user_func_array([$this,$cmdName],$cmdParams);
            $this->savePersistant();
        }
    }

    /**
    * Handles a non text message by calling the apporiate on_ method
    */
    private function handleNonTextMessage($message){
        foreach(static::$METHOD_TYPES as $v){
            if (array_key_exists($v,$message)){
                $func = "on_$v";
                return call_user_func([$this,"on_$v"],$message[$v],$message);
            }
        }
    }

    /**
    * Handles an inline query request - delegates the main task to on_inlineQuery
    * It then json encodes the returnted result of on_inlineQuery
    * And adds the inline_query_id before sending the response.
    */
    private function handleInlineQuery($inlineQuery){
        $this->chatType = "query";
        $this->userId = $inlineQuery["from"]["id"];
        $this->chatId = $inlineQuery["from"]["id"];
        $this->loadPersistant();
        $result = $this->on_inlineQuery($inlineQuery["query"],$inlineQuery["offset"]);

        //if(!$result) return

        #Json encode that result
        $result["results"] = json_encode($result["results"]);
        #Set the inline query id
        $result["inline_query_id"] = $inlineQuery["id"];
        #Respond
        $this->sendPackage("answerInlineQuery",$result);
    }

    /**
    * Sends a response to back to the Telegram servers,
    * most of the time this isn't nessesery and there is helper methods
    * @param $method - the telegram api method
    * @param $payload - Associatve array of paramaters to pass
    * @param $supressError - If error messages should be supressed, otherwise they are raised via systemError()
    */
    public function sendPackage($method,$payload=[],$supressError=false){
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL             => static::BASE_URI . static::BOT_ID . ":" . static::$BOT_KEY . "/" . $method,
            CURLOPT_POST            => true,
            CURLOPT_RETURNTRANSFER  => true,
            CURLOPT_HTTPHEADER      => ["Content-Type:multipart/form-data"],
            CURLOPT_POSTFIELDS      => $payload
        ]);
        $result = curl_exec($curl);
          if(curl_errno($curl)){
            $this->systemError("Curl error $ch when sending $method ".print_r($payload,true),"An error occoured sending the reply");
            return curl_errno($curl);
        }

        if($method === static::FILE_DOWNLOAD){
            return $result;
        }

        $result = json_decode($result,true);
        if(!$result["ok"]){
            if(!$supressError){
                $this->systemError("Telegram error ".$result["description"]." when sending $method ".print_r($payload,true),"An error occoured when sending the reply");
            }
            return $result["error_code"];
        }

        return $result["result"];
    }

    /**
    * Calls the getMe Api method - Delegates to sendPackage()
    */
    function getMe(){
        return $this->sendPackage("getMe",[]);
    }

    /**
    * Calls the sendMessage Api method - Delegates to sendPackage()
    * @param $text                  The message to send
    * @param $reply_to              the message to reply to, If true will be set the recived message's id
    * @param $chat_id               the chat id to send the message to, if obmitted will be sent to the chat the message was sent from
    * @param $reply_markup          one of the REPLY_* constants
    * @param $disable_web_preview   If web link previews should be disabled in the message, defaults to false
    * @param $parse_mode            Parsing mode to use, deafults to null (none) other option is "markdown"
    * @param $supress_error         Passed stright to sendPackage
    */
    function sendMessage($text,$reply_to=null,$chat_id=null,$reply_markup=null,$disable_web_preview=false,$parse_mode=null,$supress_error=false){
        $payload = [
            "chat_id"               => $chat_id !== null  ? $chat_id         : $this->chatId,
            "reply_to"              => $reply_to === true ? $this->messageId : $reply_to,
            "text"                  => $text,
            "disable_web_preview"   => $disable_web_preview,
        ];

        switch ($reply_markup){
            case self::REPLY_CLEAR_KEYBOARD_ALL:
                $payload["reply_markup"] = json_encode(["hide_keyboard" => true, "selective" => false]);
                break;
            case self::REPLY_CLEAR_KEYBOARD_SELECTIVE:
                $payload["reply_markup"] = json_encode(["hide_keyboard" => true, "selective" => true]);
                break;
            case self::REPLY_FORCE_ALL:
                $payload["reply_markup"] = json_encode(["force_reply" => true, "selective" => false]);
                break;
            case self::REPLY_FORCE_SELECTIVE:
                $payload["reply_markup"] = json_encode(["force_reply" => true, "selective" => true]);
                break;
            case null:
                break;
            default:
                $payload["reply_markup"] = json_encode($reply_markup);
        }
        if ($parse_mode !== null){
            $payload["parse_mode"] = $parse_mode;
        }
        return $this->sendPackage("sendMessage",$payload,$supress_error);
    }

    /**
    * Downloads the file with the given file_id
    * @parama $fileId       The identifier for the file to download
    * @parama $urlOnly      if given the file isn't downloaded but it's url is returned
    * @returns              The downloaded file (as string) or the url (valid for 1hr)
    */
    function downloadFile($fileId,$urlOnly=false){
        $fileObj = $this->sendPackage("getFile",["file_id"=>$fileId]);
        $this->infoLog($fileObj);
        $url = static::FILE_BASE_URI . static::BOT_ID . ":" . static::$BOT_KEY . "/" . $fileObj["file_path"];
        if($urlOnly){
            return $url;
        } else {
            return file_get_contents($url);
        }
    }

    /**
    * Example command: command are specified by naming them command_*
    * The doc string is used to provide help to the user via this inbuilt /help command
    * The paramaters can be anythin you want, they are populated by what the user types splitting on spaces
    * TODO: Provide help for certain on_* functions
    */
    /**
    * [command] Provides help on the given command or general help if no command is given.
    */
    protected function command_help($command=null){
        if ($command){
            $method = "command_$command";
            $inspect =  new ReflectionMethod($this, $method);
            $docStrings = explode("\n",$inspect->getDocComment());
            $msg = "/$command - ";
            foreach($docStrings as $n => $line){
                if ($n == 0 || $n == count($docString)-1) continue;
                $msg .= substr(trim($line),2) . "\n";
            }
        } else {
            $msg = "";
            //Check on_inlineQuery
            $inspect = new ReflectionMethod($this, "on_inlineQuery");
            if($inspect->getDeclaringClass()->getName() !== "Tg"){
                $docString = $inspect->getDocComment();
                $docString = substr(trim(explode("\n",$docString)[1]),2);
                $msg .= static::BOT_USERNAME ." is an inline bot which $docString";
                $msg .= "For more help use /helpinline\n";
            }
            //Check commands_*
            foreach(get_class_methods($this) as $method){
                if (substr($method, 0, strlen("command_")) === "command_"){
                    $cmdName = substr($method, strlen("command_"));
                    $inspect =  new ReflectionMethod($this, $method);
                    $docString = $inspect->getDocComment();
                    if ($docString){
                        $docString = substr(trim(explode("\n",$docString)[1]),2);
                        $msg .= "/$cmdName $docString\n";
                    }
                }
            }
        }
        $this->sendMessage($msg);
    }

    //Provides the docstring for on_inlineQuery if defined
    //There is no docstring for this function so it dosen't apear in /help
    //If on_inlineQuery is overloaded a keyboard button to start an inline chat will apear
    protected function command_helpinline(){
        $inspect = new ReflectionMethod($this, "on_inlineQuery");
        $keyboard = null;
        if($inspect->getDeclaringClass()->getName() !== get_class()){
            $docStrings = explode("\n",$inspect->getDocComment());
            $msg = "";
            foreach($docStrings as $n => $line){
                if ($n == 0 || $n == count($docString)-1) continue;
                $msg .= substr(trim($line),2) . "\n";
                $keyboard = [inline_keyboard => [[["switch_inline_query" => "", "text" => "Try it"]]]];
            }
        } else {
            $msg = "This is not an inline query bot, for general command help try /help";
        }
        $this->sendMessage($msg,null,null,$keyboard);
    }

    //The follow functions are hooks to be overridden

    /**
    * Called after the user's database record is first created.
    */
    function on_firstRun(){}

    /**
    * Called when an inline query request is passed to the bot
    * @param $query     the text passed in the query
    * @param $offset    the pagation offset
    * @return Array:
    *     results     => Array of query results #TODO add convience methods to make this easier
    *     next_offset => What the next $offset paramater should be as a String
    *     is_personal => If the result should be private. If False the result are cahced are cached by Telegram.
    */
    function on_inlineQuery($query,$offset){
        $this->systemError("Bot not designed for inlineQuery","Bot not designed for inlineQuery");
    }
}
