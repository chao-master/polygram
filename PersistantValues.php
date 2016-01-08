<?php
    class PersistanceMap implements ArrayAccess{

        private $values = [];
        private $dirty = false;
        private $parent;
        private $dbPath;
        private $path;
        private $baseValues;
        private $userId;

        private $populated;

        static $schemaBase = ["_user_ INT NOT NULL PRIMARY KEY"];

        public function __construct($parent){
            $this->parent = $parent;
        }

        //Array interface
        public function offsetSet($offset, $value) {
            if (substr($offset,0,1) == "_" || !isset($this->values[$offset])) return;
            $this->populate();
            $this->values[$offset] = $value;
            $this->dirty = true;

        }
        public function offsetExists($offset) {
            $this->populate();
            return isset($this->values[$offset]);
        }
        public function offsetUnset($offset) {
            if (substr($offset,0,1) == "_" || !isset($this->values[$offset])) return;
            $this->populate();
            $this->values[$offset] = $this->parent->getPrivateVals()[$offset];
            $this->dirty = true;
        }
        public function offsetGet($offset) {
            $this->populate();
            return isset($this->values[$offset]) ? $this->values[$offset] : null;
        }

        //Database functions
        public function populate(){
            if ($populated) return;
            try{
                $db = new SQLite3("persistance.db",SQLITE3_OPEN_READWRITE);
            } catch (Exception $e) {
                $db = $this->makeTable($baseValues);
            }
            $stmt = $db->prepare("SELECT * FROM private WHERE _user_=:uid");
            $stmt->bindValue(":uid",$userId);
            $nObj = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
            if($nObj){
                $this->userVals = $nObj;
            } elseif ($chatType != "query") {
                $stmt = $db->prepare("INSERT INTO private (\"_user_\") VALUES (:uid)");
                $stmt->bindValue(":uid",$this->parent->getUseId());
                $stmt->execute();
                $this->userVals = $this->parent->getPrivateVals()[$offset];;
            }
        }

        private function makeSchema(&$cols,$map,$prefix=""){
            foreach($map as $key => $val){
                $type = gettype($val);
                if ($type == "array"){
                    $this->makeSchema($cols,$val, $prefix.$key."." );
                } else {
                    $key = '"' . SQLite3::escapeString($prefix.$key) . '"';
                    if ($type == "string"){
                        $val = "'".SQLite3::escapeString($val)."'";
                    }
                    $cols[] = "$key $type DEFAULT $val";
                }
            }
        }

        private function makeTable(&$baseValues){
            $db = new SQLite3("persistance.db");
            $cols = static::$schemaBase;
            $this->makeSchema($cols,$this->parent->getPrivateVals());
            $colString = "(".implode(",",$cols).")";
            $tableName = '"'.implode(".",$this->path).'"';
            error_log("CREATE TABLE $tableName $colString;");
            $db->exec("CREATE TABLE $tableName $colString;");
        }
    }
?>
