<?php
    include_once "Tg.php";
    class TgSqlite extends Tg {
        /**
        * Creates the persistance database and sets up the tables
        */
        private function makeDatabase(){
            $db = new SQLite3(get_class($this).".db");
            $db->busyTimeout(5000);
            if(static::$PRIVATE_VALS){
                $cols = ["_user_ INT PRIMARY KEY NOT NULL","_last_ STRING"];
                foreach(static::$PRIVATE_VALS as $key => $val){
                    $type = gettype($val);
                    $key = '"'.$db->escapeString($key).'"';
                    if ($type == "string"){
                        $val = "'".$db->escapeString($val)."'";
                    }
                    $cols[] = "$key $type DEFAULT $val";
                }
                $colString = "(".implode(",",$cols).")";
                $db->exec("CREATE TABLE private $colString;");
            }
            return $db;
        }

        /**
        * Saves the persistance userVals to the database
        */
        protected function savePersistant(){
            $db = new SQLite3(get_class($this).".db",SQLITE3_OPEN_READWRITE);
            $db->busyTimeout(5000);
            if(static::$PRIVATE_VALS){
                $sets = [];
                foreach($this->userVals as $key => $val){
                    $key = '"'.$db->escapeString($key).'"';
                    if(is_string($val)){
                        $val = "'".$db->escapeString($val)."'";
                    } elseif(is_null($val)){
                        $val = "NULL";
                    }
                    $sets[] = "$key = $val";
                }
                $setsString = implode(",",$sets);
                $this->infoLog("UPDATE private SET $setsString WHERE _user_ = $this->userId;");
                if (!$db->exec("UPDATE private SET $setsString WHERE _user_ = $this->userId;")){
                    $this->systemError($db->lastErrorMsg(),"A database error occoured when saving the result of your action");
                }

            }
            $db->close();
        }

        /**
        * Loads the persistance userVals from the database
        * If the user dosen't exist a record is created for them and on_firstRun is called.
        */
        protected function loadPersistant(){
            try{
                $db = new SQLite3(get_class($this).".db",SQLITE3_OPEN_READWRITE);
                $db->busyTimeout(5000);
            } catch (Exception $e) {
                $db = $this->makeDatabase();
            }
            if($this->chatType == "private" || $this->chatType == "query"){
                if(static::$PRIVATE_VALS){
                    $stmt = $db->prepare("SELECT * FROM private WHERE _user_=:uid");
                    $stmt->bindValue(":uid",$this->userId);
                    $nObj = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
                    if($nObj){
                        $this->userVals = $nObj;
                    } elseif ($this->chatType != "query") {
                        $stmt = $db->prepare("INSERT INTO private (\"_user_\") VALUES (:uid)");
                        $stmt->bindValue(":uid",$this->userId);
                        $stmt->execute();
                        $this->userVals = static::$PRIVATE_VALS;
                        $this->on_firstRun();
                    }
                } else {
                    $this->userVals = [];
                }
            }
            $db->close();
        }
    }
?>
