SimpleCsv
=========

About...
--------

Simple CSV is a library for interacting with CSV files.

Installation...
---------------

Make sure you have these elements in your `composer.json` file:

```
    "repositories":[
        {
            "type": "vcs",
            "url": "git@github.com:rogerthomas84/simplecsv.git"
        }
    ],
    
    // More composer content.

    "require": {
        "rogerthomas84/simplecsv": ">=1.0.0"
    }
```

Usage...
--------

```php
<?php
// Pass the file to the construct
$reader = new \SimpleCsv\Reader(
    '/path/to/my/file.csv',
    ',', // delimiter ',' is the default
    '"', // enclosure character '"' is the default
    '\\' // escape characted, a single slash \ is the default
);

// Open the file pointer.
$reader->openFile();

// Read or set the headers.
$reader->readHeaders();

// Or:
// $reader->setHeaders(['first_name', 'last_name']);

// Optionally map the headers to new keys:
// $reader->setHeaderMap(['first_name' => 'firstName']);

// Iterate the lines
while (null !== $data = @$reader->getNextLine()) {
    if ($data !== false) {
        // do something with $data
    } else {
        echo 'Line #' . $reader->getLastLineNumber() . ' was invalid.';
    }
}

// Or, instead of reading line by line:
// $reader->getAllData();
// You cannot use getAllData after reading a line.


// Close the file
$reader->closeFile();

```

Unit testing...
---------------

```
$ ./vendor/bin/phpunit -c phpunit.xml
```
