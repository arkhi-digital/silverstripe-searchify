<?php

class SearchifyIndexAllTasks extends BuildTask {

    public function run($request) {
        echo "Successfully indexed/updated " . Searchify::inst()->indexAll() . " pages.";
    }

}