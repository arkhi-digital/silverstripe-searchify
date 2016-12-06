<?php
if (!getenv('TRAVIS')) {
    Page_Controller::add_extension('SearchifyPage_ControllerExtension');
}