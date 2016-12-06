<?php

class Searchify extends Object
{

    /**
     * @var Indextank_Api
     */
    protected $client;

    /**
     * @var Indextank_Index
     */
    protected $index;

    /**
     * Get a singleton instance. Use the default Object functionality
     * @return \Searchify
     */
    public static function inst()
    {
        return self::singleton();
    }

    /**
     * Searchify constructor.
     */
    public function __construct()
    {

        $apiUrl = $this->getApiUrl();
        $this->client = new Indextank_Api($apiUrl);

        if (!$this->config()->index) {
            user_error(
                _t(
                    "Searchify.IndexUndefined",
                    "You must define an index for Searchify to use. Please see the README"
                ),
                E_USER_ERROR
            );
        }

        $this->setIndex($this->config()->index);

        parent::__construct();
    }

    /**
     * @param $key
     *
     * @return $this
     */
    public function setIndex($key)
    {
        $indexes = $this->getIndexList();
        if (!isset($indexes[$key])) {
            if (!$this->config()->make_index) {
                user_error(
                    _t(
                        "Searchify.IndexNonExistent",
                        "The index {key} does not exist",
                        "The error message shown when an index does not exist, and configuration options disallow the creation of it",
                        [
                            'key' => $key
                        ]
                    ),
                    E_USER_ERROR
                );
            }

            $createOptions = [
                'public_search' => true
            ];

            $this->index = $this->client->create_index($key, $createOptions);

            while (!$this->index->has_started()) {
                sleep(1);
            }

            return $this;
        }

        $this->index = $this->client->get_index($key);

        return $this;
    }

    /**
     * @return \Indextank_Index|null
     */
    public function getIndex()
    {
        return $this->index;
    }

    /**
     * Adds a page to the specified index
     * @param \Page|Object $record
     * @return null
     */
    public function addPage($record)
    {
        if (!$this->index instanceof \Indextank_Index) {
            user_error(
                _t(
                    "Searchify.AddPageRace",
                    "addPage() called before setIndex()"
                ),
                E_USER_ERROR
            );
        }

        // ensure the page type isn't blacklisted (eg you wouldn't want to index an ErrorPage)
        if ($this->isBlacklisted($record->ClassName)) {
            return false;
        }

        $docDetails = [
            "text" => $this->discover($record),
            "title" => $record->Title,
            "timestamp" => strtotime($record->LastEdited)
        ];

        try {
            $result = $this->index->add_document($record->ID, $docDetails);

            if ($result->status !== 200) {
                $message = _t(
                    "Searchify.ErrorAddingToIndex",
                    "There was an error adding this document"
                );

                user_error($message, E_USER_NOTICE);
                $this->setToast($message);
                return false;
            }

            $this->setToast(
                _t(
                    "Searchify.IndexUpdated",
                    "Your searchify index has been updated."
                )
            );

            if (!(int)$record->SearchifyIndexState) {
                $record->SearchifyIndexState = 1;
                $record->write();
            }

            return true;
        } catch (\Indextank_Exception_HttpException $e) {
            // todo, i18n the messages
            $this->setToast($e->getMessage());
            return false;
        }
    }

    /**
     * @param ArrayList|DataList $records
     * @internal Page|Object $record
     *
     * @return bool
     */
    public function addPages($records)
    {
        if (!$records->count()) {
            return false;
        }

        $docs = [];

        foreach ($records as $record) {
            if ($this->isBlacklisted($record->ClassName)) {
                continue;
            }

            $docs[] = [
                "docid" => $record->ID,
                "fields" => [
                    "text" => $this->discover($record),
                    "title" => $record->Title,
                    "timestamp" => strtotime($record->LastEdited)
                ]
            ];
        }

        $result = $this->index->add_documents($docs);

        if ($result->status !== 200) {
            $message = _t(
                "Searchify.ErrorAddingToIndex",
                "There was an error adding this document"
            );

            user_error($message, E_USER_NOTICE);
            $this->setToast($message);
            return false;
        }

        foreach ($records as $record) {
            if (!(int)$record->SearchifyIndexState) {
                $record->SearchifyIndexState = 1;
                $record->write();
            }
        }

        $this->setToast(
            _t(
                "Searchify.IndexUpdated",
                "Your searchify index has been updated."
            )
        );
        return true;
    }

    /**
     * Removes a page from the specified index
     * @param \Page|Object $record
     * @return bool
     */
    public function removePage($record)
    {
        if (!$this->index instanceof \Indextank_Index) {
            user_error(
                _t(
                    "Searchify.RemovePageRace",
                    "removePage() called before setIndex()"
                ),
                E_USER_ERROR
            );
        }

        $result = $this->index->delete_document($record->ID);

        if ($result->status !== 200) {
            $message = _t(
                "Searchify.ErrorRemovingFromIndex",
                "There was an error removing this document"
            );

            user_error($message, E_USER_NOTICE);
            $this->setToast($message);
            return false;
        }

        if ((int)$record->SearchifyIndexState) {
            $record->SearchifyIndexState = 0;
            $record->write();
        }

        $this->setToast(
            _t(
                "Searchify.RemovedFromIndex",
                "This document has been removed from the index as a result of Show In Search being disabled, or is not publicly visible"
            )
        );

        return true;
    }

    /**
     * @todo
     * @param ArrayList $records
     */
    public function removePages(ArrayList $records)
    {

    }

    /**
     * Searches the currently active index
     *
     * @param string $query The search term
     * @param array|null $snippetFields An array of fields to extra a relevant snippet from for the search
     * @param array|null $fetchFields An array of extra fields to get
     *
     * @return mixed
     */
    public function search($query, array $snippetFields = null, array $fetchFields = null)
    {
        if (!$snippetFields) {
            $snippetFields = [
                'text'
            ];
        }

        if (!$fetchFields) {
            $fetchFields = [
                'title',
                'timestamp'
            ];
        }

        return $this->index->search(
            $query,
            null,
            null,
            null,
            $snippetFields,
            $fetchFields
        );
    }

    /**
     * @return string
     */
    public function getApiUrl()
    {
        if (!defined('SEARCHIFY_API_URL')) {
            user_error(
                _t(
                    "Searchify.ApiUrlUndefined",
                    "You must define the SEARCHIFY_API_URL as a constant"
                ),
                E_USER_ERROR
            );
        }

        return SEARCHIFY_API_URL;
    }

    /**
     * Returns a list of indexes from the API
     * @return array
     */
    public function getIndexList()
    {
        return (array)$this->client->list_indexes();
    }

    /**
     * Fetch the Searchify configuration
     *
     * @todo This accessor is incorrect, forClass('Searchify') wouldn't work though?
     * @return \ArrayData
     */
    public static function config()
    {
        return ArrayData::create(\Config::inst()->get('Searchify', 'settings'));
    }

    /**
     * Discovers custom field names relevant to the PageType from the DB configuration, at the moment it only checks
     * for HTMLText as anything else would be a gamble. Relationships are not respected.
     *
     * If this functionality has been disabled via YAML configuration, then only the DataObject::$Content value will
     * be returned.
     *
     * @param \Page|Object $record
     * @return mixed
     */
    public function discover($record)
    {
        $config = \Config::inst()->get($record->ClassName, 'db');

        $content = [
            $record->Content
        ];

        if (!$config || !$this->config()->discover) {
            return Convert::html2raw($content[0]);
        }

        // unset useless information
        unset(
            $config['URLSegment'],
            $config['MenuTitle'],
            $config['ExtraMeta'],
            $config['ShowInMenus'],
            $config['ShowInSearch'],
            $config['Sort'],
            $config['HasBrokenFile'],
            $config['HasBrokenLink'],
            $config['ReportClass'],
            $config['CanViewType'],
            $config['CanEditType'],
            $config['Version'],
            $config['Content'],
            $config['Title']
        );

        if (empty($config)) {
            return Convert::html2raw($content[0]);
        }

        foreach ($config as $fieldName => $fieldType) {
            if ($fieldName !== 'MetaDescription' && (!strstr($fieldType, 'HTMLText') || !isset($record->{$fieldName}))) {
                continue;
            }

            $content[] = $record->{$fieldName};
        }

        return Convert::html2raw(implode("<br/><br/>", $content));

    }

    /**
     * Sets the X-Status header which creates the toast-like popout notification
     *
     * @param $message
     *
     * @return $this
     */
    private function setToast($message)
    {
        Controller::curr()->response->addHeader('X-Status', rawurlencode('Searchify: ' . $message));
        return $this;
    }

    /**
     * Determines if a Page is publicly visible
     *
     * @param DataObject|Page|Object $record
     * @return bool
     */
    public function isPublishable($record)
    {
        if (in_array($record->CanViewType, ["LoggedInUsers", "OnlyTheseUsers"])) {
            return false;
        }

        $top = $this->getTopLevelParent($record);

        if ($top->CanViewType == 'Anyone') {
            return true;
        }

        // only "Inherit" remains
        $siteConfig = SiteConfig::get()->byID(1);

        if ($siteConfig->CanViewType == 'Anyone') {
            return true;
        }

        return false;

    }

    /**
     * Recursively finds the very most top level parent of a Page
     *
     * @param DataObject|Object|Page $record
     * @return DataObject
     */
    private function getTopLevelParent($record)
    {
        if ($record->ParentID == 0) {
            return $record;
        }

        $record = SiteTree::get()->byID($record->ParentID);
        return $this->getTopLevelParent($record);
    }

    /**
     * Indexes all publicly visible, published pages
     */
    public function indexAll()
    {
        $pages = SiteTree::get();

        if (!$pages) {
            user_error(
                _t(
                    "Searchify.NoPagesExist",
                    "No pages were found for indexing"
                ),
                E_USER_ERROR // maybe adjust this to something less fatal
            );
        }

        $indexed = 0;

        foreach ($pages as $page) {
            if (!$this->isPublishable($page)) {
                continue;
            }

            if ($this->addPage($page)) {
                $indexed++;
            }
        }

        return $indexed;
    }

    /**
     * Fetches page blacklist from configuration
     *
     * @return mixed
     */
    public function getBlacklist()
    {
        return static::config()->page_blacklist;
    }

    /**
     * Checks if a page type is blacklisted
     *
     * @param $pageType
     * @return bool
     */
    public function isBlacklisted($pageType)
    {
        $blacklist = $this->getBlacklist();

        if (!is_array($blacklist) || in_array($pageType, $blacklist)) {
            return false;
        }
        return true;
    }
}
