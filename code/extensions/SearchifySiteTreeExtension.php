<?php
class SearchifySiteTreeExtension extends SiteTreeExtension {
    private static $db = [
        'SearchifyIndexState' => 'Int(0)'
    ];

    /**
     * @param SiteTree $original
     */
    public function onAfterPublish(&$original)
    {

        $record = $this->owner;

        if (!$record) {
            parent::onAfterPublish($original);
        }

        if ($record->ShowInSearch && Searchify::inst()->isPublishable($record)) {
            Searchify::inst()->addPage($record);
        } else {
            Searchify::inst()->removePage($record);
        }

        parent::onAfterPublish($original);

    }

    /**
     *
     */
    public function onAfterUnpublish()
    {
        $record = $this->owner;

        Searchify::inst()->removePage($record);

        parent::onAfterUnpublish();
    }


}