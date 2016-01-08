<?php

include_once "Tg.php";

class Derpibooru extends Tg {
    const BOT_ID        = "166234207";
    const BOT_USERNAME  = "DerpiDeliveryBot";

    static $PRIVATE_VALS = [
        "filter" => "",
        "key" => ""
    ];

    //TODO Allow !noflag to be used with ![related]
    function parseQueryTerms($query,$page){
        $termsIn = explode(",",$query);
        $termsOut = [];
        $rtn = [
            "url" => "/search.json",
            "getKey" => "search",
            "params" => [],
            "noKey" => false,
            "page" => $page
        ];
        //Check for singular number
        if(count($termsIn) == 1){
            if ( preg_match('/^![1-9][0-9]*$/',$termsIn[0])){
                $id = substr($termsIn[0],1);
                $rtn["url"] = "/related/$id.json";
                $rtn["getKey"] = "images";
                return $rtn;
            }
        }
        //Go over each tag checking it for specials
        foreach($termsIn as $term){
            $term = trim($term);
            if(!$term) continue;
            if (substr($term,0,1) == "!"){
                //Flag handling
                switch (substr($term,1)){
                    case "faves":
                        $rtn["params"]["faves"] = "only";   break;
                    case "-faves":
                        $rtn["params"]["faves"] = "not";    break;
                    case "uploads":
                        $rtn["params"]["uploads"] = "only"; break;
                    case "-uploads":
                        $rtn["params"]["uploads"] = "not";  break;
                    case "watched":
                        $rtn["params"]["watched"] = "only"; break;
                    case "-watched": case "-watch":
                        $rtn["params"]["watched"] = "not";  break;
                    case "upvotes":
                        $rtn["params"]["upvotes"] = "only"; break;
                    case "-upvotes":
                        $rtn["params"]["upvotes"] = "not"; break;
                    case "nokey":
                        $rtn["noKey"] = true; break;
                }
            } else {
                $termsOut[] = $term;
            }
        }
        if($termsOut){
            $rtn["params"]["q"] = implode(", ",$termsOut);
        } else {
            $rtn["params"]["q"] = "!";
        }
        return $rtn;
    }

    function makeRequest($request){
        $getKey = $request["getKey"];
        $url = 'https://derpiboo.ru' .$request["url"] . '?';
        $params = $request["params"];

        //Add key if it exists and is wanted
        if(!$request["noKey"] && $this->userVals["key"]){
            $params["key"] = $this->userVals["key"];
        }

        //Build querystring up
        $query=[];
        foreach($params as $key => $val){
            $query[] = $key . '=' . urlencode($val);

        }
        $url .= implode('&',$query);

        $result = json_decode(file_get_contents($url),true);

        if (isset($result[$getKey])){
            return $result[$getKey];
        } else {
            return [];
        }
    }

    /**
    * searchs derpibooru for images.
    * The search system attempts to immitate that on the site with one modification
    * terms starting with ! are used for special flags
    * !faves !uploads !watched & !upvotes limit the search to your faves, uplods, watched images and upvoted images
    *  - You can also modify them with a - (eg !-faves) to exclude them instead
    *  - These only work if you have a key set with /setkey
    * !nokey Makes an anamonous request, ignoring your key if you have it set with /setkey
    * ![image id] Searches for images related to the given image - only works if it's the only term
    */
    function on_inlineQuery($query,$offset){
        if($offset<1) $offset = 1;
        if($query){
            $request = $this->parseQueryTerms($query,$offset);
        } else {
            $request = [
                "url" => "/lists/top_scoring.json",
                "getKey" => "images",
                "params" => [],
                "noKey" => false,
                "page" => $page
            ];
            if ($this->userVals["key"]){
                $request["url"] = "/images/watched.json";
            }
        }


        $imageList = $this->makeRequest($request);

        $results = [];
        foreach($imageList as $q){
            $type = $q["original_format"]=="gif"?"gif":"photo";
            $id = strval($q["id_number"]);
            $results[] = [
                "type" => $type,
                "id" => $id,
                "${type}_url" => "https:".$q["representations"]["large"],
                "${type}_width" => $q["width"],
                "${type}_height" => $q["height"],
                "thumb_url" => "https:".$q["representations"]["thumb"],
                "caption" => "https://derpiboo.ru/$id"
            ];
        }

        return [
            "results" => $results,
            "next_offset" => $imageList ? strval($offset+1) : "",
            "is_personal" => True
        ];
    }

    function changeFilter($filter,$preserve){
        $filterParts = explode(",",$filter);
        foreach($filterParts as &$f){
            $f = "-".trim($f);
        }
        if($preserve){
            $filterParts[] = $this->userVals["filter"];
        }
        $this->userVals["filter"] = implode(",",$filterParts);
        $this->command_getFilter();
    }

    /**
    * [tags (comma seperated list)] - Sets your filter list to the given tags
    * This Pervents them showing up in results (even if you search for them).
    */
    function command_setFilter($filter){
        $this->changeFilter($filter,False);
    }

    /**
    * [tags (comma seperated list)] - Adds the given tags to your filter list.
    * This Pervents them showing up in results (even if you search for them).
    */
    function command_addFilter($filter){
        $this->changeFilter($filter,True);
    }

    /**
    * Shows your current filter list
    */
    function command_getFilter(){
        $filterParts = explode(",",$this->userVals["filter"]);
        foreach($filterParts as &$f){
            $f = substr($f,1);
        }
        $this->sendMessage("Filter current set to exlude: ".implode(", ",$filterParts));
    }

    /**
    * [key] - Sets/removes the api key used for your requests
    * This will make the bot use your own filters.
    * The hash can be found on derpiboo.ru/users/edit
    */
    function command_setKey($key=""){
        $this->userVals["key"] = $key;
        if (!$key){
            $this->sendMessage("Key removed from account");
        } else {
            $this->sendMessage("Key added to account");
        }
    }

}

?>
