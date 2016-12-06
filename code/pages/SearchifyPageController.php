<?php

class SearchifyPage_Controller extends Page_Controller
{
    private static $allowed_actions = [
        'index',
    ];

    public function index(SS_HTTPRequest $r)
    {
        $results = Searchify::inst()->search($r->requestVar('q'));
        $resultStack = array();

        foreach ($results->results as $result) {
            $page = SiteTree::get()->byID($result->docid);
            if (!$page) {
                // todo delete from index? docid references a page id that doesn't exist in the SiteTree
                continue;
            }

            $snippet_text = HTMLText::create();
            $snippet_text->setValue(Searchify::inst()->discover($page));
            $snippet_summary = HTMLText::create();
            $snippet_summary->setValue($snippet_text->ContextSummary(500, $r->requestVar('q')));

            $resultStack[] = $this->renderWith(
                "SearchifyResult",
                array(
                    'Title' => $page->Title,
                    'SearchLink' => $page->Link(),
                    'Snippet' => $snippet_summary,
                    'LastEdited' => $page->LastEdited
                )
            );
        }

        $matches = $results->matches;

        if ($results->matches) {
            $results = HTMLText::create();
            $results->setValue(implode("<p>&nbsp;</p>", $resultStack));
        } else {
            $results = false;
        }

        return $this->render([
            'Title' => 'Search Results',
            'Content' => $this->renderWith(
                "SearchifyResultsHolder",
                array(
                    "QueryString" => Convert::xml2raw($r->requestVar('q')),
                    "Matches" => $matches,
                    "Results" => $results
                )
            )
        ]);
    }


}

class SearchifyPage_ControllerExtension extends Extension
{
    private static $allowed_actions = array(
        'SearchForm',
    );

    public function SearchForm()
    {
        return SearchForm::create(
            $this,
            "SearchForm",
            FieldList::create(
                TextField::create('q')
                    ->setTitle(null)
                    ->setValue(ContentController::singleton()->getRequest()->getVar('q'))
                    ->setAttribute('placeholder', _t(
                        "Searchify.SearchLabel",
                        "Search"
                    ))
            ),
            FieldList::create(FormAction::create('search', 'L'))
        )
            ->setFormMethod('GET')
            ->setFormAction(
                Controller::join_links(
                    Director::baseURL(),
                    "search"
                )
            );
    }
}