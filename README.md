# silverstripe-searchify
[![Build Status](https://travis-ci.org/steadlane/silverstripe-searchify.svg?branch=master)](https://travis-ci.org/steadlane/silverstripe-searchify) [![Code Climate](https://codeclimate.com/github/steadlane/silverstripe-searchify/badges/gpa.svg)](https://codeclimate.com/github/steadlane/silverstripe-searchify)


## Introduction
This module is a complete replacement for SilverStripes built in search functionality.

It takes advantage of [Searchify's](http://www.searchify.com/)

## Installation

This module only supports installation via composer:

```
composer require steadlane/silverstripe-searchify
```

- Run `/dev/build` afterwards and `?flush=1` for good measure for SilverStripe to become aware of this module
- Then run `/dev/tasks/SearchifyIndexAllTask` to automatically index any publicly visible, and searchable pages found in your `SiteTree`


## Features
- Automatically index a page when it's published
    - Will respect the "Show In Search" CMS Page option
- Removes pages from index that have been unpublished or where "Show In Search" has been disabled
- Customisable Page Blacklist (e.g You wouldn't want to index pages of type `ErrorPage`)
- Content Discover
    - It is extremely common that custom PageTypes will have custom `HTMLText` fields defined, if `discover` is enabled then this module will detect these fields and add them to the index, therefore allowing them to be also searchable

## Required Configuration
You need only define the API URL provided by Searchify in `mysite/_config.php`:

```php
define('SEARCHIFY_API_URL', 'http://:example@api.searchify.com');
```

## Optional Configuration
**searchify/_config/searchify.yml**:

```yaml
Searchify:
  settings:
    index: MyIndex # The name of the index to use
    make_index: true # If the index doesn't exist, create it
    discover: true # If set to true, this module will adapt to any PageType it is given. For more information see the README.md
    page_blacklist: # Allows you to blacklist certain Page Types, eg you wouldn't want to index an ErrorPage
      - ErrorPage
```

## Contributing

If you feel you can improve this module in any way, shape or form please do not hesitate to submit a PR for review.

## Bugs / Issues

To report a bug or an issue please use our [issue tracker](https://github.com/steadlane/silverstripe-searchify/issues).

## License

This module is distributed under the [BSD-3 Clause](https://github.com/steadlane/silverstripe-searchify/blob/master/LICENSE) license.