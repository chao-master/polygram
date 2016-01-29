<?php
    include_once "Tg.php";
    class TgSession extends Tg {
        /**
        * Saves the persistance userVals to the session
        */
        protected function savePersistant(){
            $_SESSION = $this->userVals;
            session_write_close();
        }

        /**
        * Loads the persistance userVals from the database
        * If the user dosen't exist a record is created for them and on_firstRun is called.
        */
        protected function loadPersistant(){
            if($this->chatType == "private" || $this->chatType == "query"){
                if(static::$PRIVATE_VALS){
                    session_save_path("userData");
                    session_id($this->userId);
                    session_start();
                    if(count($_SESSION) > 0){
                        $this->userVals = $_SESSION;
                    } elseif ($this->chatType != "query") {
                        $this->on_firstRun();
                        $this->userVals = static::$PRIVATE_VALS;
                    }
                } else {
                    $this->userVals = [];
                }
            }
        }
    }
?>
