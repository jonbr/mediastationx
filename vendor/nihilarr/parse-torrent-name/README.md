
# parse-torrent-name

> Extract media information from torrent-like filename

A PHP port of [Divij Bindlish](https://github.com/divijbindlish)'s Python port of [Jānis](https://github.com/jzjzjzj)' awesome [library](https://github.com/jzjzjzj/parse-torrent-name) written in JavaScript.

Extract all possible media information present in filenames. Multiple regex rules are applied on filename string each of which extracts corresponding information from the filename. If a regex rule matches, the corresponding part is removed from the filename. In the end, the remaining part is taken as the title of the content.

## Why?

Online APIs by providers like [TMDb](https://www.themoviedb.org/documentation/api), [TVDb](http://thetvdb.com/wiki/index.php?title=Programmers_API) and [OMDb](http://www.omdbapi.com/) don't react to well to search queries which include any kind of extra information. To get proper results from these APIs, only the title of the content should be provided as the search query where this library comes into play. The accuracy of the results can be improved by passing in the year which can also be extracted using this library.

## Requirements

* [PHP](https://secure.php.net/manual/en/install.php) >= 5.3.29

## Install

### Composer
```bash
composer require nihilarr/parse-torrent-name
```
### Non-Composer
```php
require('PTN.php'); // Require PTN/PTN.php file
```

## Usage

```php
$ptn = new PTN();
$results = $ptn->parse('A freakishly cool movie or TV episode');

var_dump($results); // All details that were parsed
```

PTN works well for both movies and TV episodes. All meaningful information is extracted and returned together in a dictionary. The text which could not be parsed is returned in the `excess` field.

### Movies

```php
$ptn->parse('San Andreas 2015 720p WEB-DL x264 AAC-JYK');
# array(
#     'group' => 'JYK',
#     'title' => 'San Andreas',
#     'resolution' => '720p',
#     'codec' => 'x264',
#     'year' =>  '2015',
#     'audio' => 'AAC',
#     'quality' => 'WEB-DL'
# );

$ptn->parse('The Martian 2015 540p HDRip KORSUB x264 AAC2 0-FGT');
# array(
#     'group' => '0-FGT',
#     'title' => 'The Martian',
#     'resolution' => '540p',
#     'excess' => ['KORSUB', '2'],
#     'codec' => 'x264',
#     'year' => 2015,
#     'audio' => 'AAC',
#     'quality' => 'HDRip'
# );
```

### TV Episodes

```php
$ptn->parse('Mr Robot S01E05 HDTV x264-KILLERS[ettv]');
# array(
#     'episode' => 5,
#     'season' => 1,
#     'title' => 'Mr Robot',
#     'codec' => 'x264',
#     'group' =>  'KILLERS[ettv]'
#     'quality' => 'HDTV'
# );

$ptn->parse('friends.s02e01.720p.bluray-sujaidr');
# array(
#     'episode' => 1,
#     'season' => 2,
#     'title' => 'friends',
#     'resolution' => '720p',
#     'group' => 'sujaidr',
#     'quality' => 'bluray'    
# );
```

### Note

PTN does not gaurantee the fields `group`, `excess` and `episodeName` as these fields might be interchanged with each other. This shouldn't affect most applications since episode name can be fetched from an online database after getting the season and episode number correctly.

### Parts extracted

* audio
* codec
* container
* episode
* episodeName
* excess
* extended
* garbage
* group
* hardcoded
* language
* proper
* quality
* region
* repack
* resolution
* season
* title
* website
* widescreen
* year

## Contributing

Take a look at the open [issues](https://github.com/jzjzjzj/parse-torrent-name/issues) on the original JavaScript project and submit a PR!

Take a look at the open [issues](https://github.com/divijbindlish/parse-torrent-name/issues) on the Python port and submit a PR!

## License

MIT © [Drew Smith](https://www.nihilarr.com)
