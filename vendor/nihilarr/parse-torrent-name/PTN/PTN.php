<?php
/**
* Parse Torrent Name (PTN)
*
* PHP port of parse-torrent-name written in Python.
*
* Javascript version by jzjzjzj
* https://github.com/jzjzjzj/parse-torrent-name
*
* Python version by divijbindlish
* https://github.com/divijbindlish/parse-torrent-name
*
* Copyright (c) 2014 - 2018, British Columbia Institute of Technology
*
* Permission is hereby granted, free of charge, to any person obtaining a copy
* of this software and associated documentation files (the "Software"), to deal
* in the Software without restriction, including without limitation the rights
* to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
* copies of the Software, and to permit persons to whom the Software is
* furnished to do so, subject to the following conditions:
*
* The above copyright notice and this permission notice shall be included in
* all copies or substantial portions of the Software.
*
* THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
* IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
* FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
* AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
* LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
* OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
* THE SOFTWARE.
*
* @package   PTN
* @author    Drew Smith
* @copyright copyright (c) 2018, Nihilarr (https://www.nihilarr.com)
* @license	 http://opensource.org/licenses/MIT	MIT License
* @link      https://gitlab.com/nihilarr/parse-torrent-name
* @version   0.0.1
*/

namespace Nihilarr;

class PTN {

    public $torrent;
    public $excess_raw;
    public $group_raw;
    public $start;
    public $end;
    public $title_raw;
    public $parts;

    public $patterns = array(
        array('season' => '(s?([0-9]{1,2}))'),
        array('episode' => '([ex]([0-9]{2})(?:[^0-9]|$))'),
        array('year' => '([\[\(]?((?:19[0-9]|20[01])[0-9])[\]\)]?)'),
        array('resolution' => '([0-9]{3,4}p)'),
        array('quality' => '((?:PPV\.)?[HP]DTV|(?:HD)?CAM|B[DR]Rip|(?:HD-?)?TS|(?:PPV )?WEB-?DL(?: DVDRip)?|HDRip|DVDRip|DVDRIP|CamRip|W[EB]BRip|BluRay|DvDScr|hdtv|telesync)'),
        array('codec' => '(xvid|[hx]\.?26[45])'),
        array('audio' => '(MP3|DD5\.?1|Dual[\- ]Audio|LiNE|DTS|AAC[.-]LC|AAC(?:\.?2\.0)?|AC3(?:\.5\.1)?)'),
        array('group' => '(- ?([^-]+(?:-={[^-]+-?$)?))$'),
        array('region' => 'R[0-9]'),
        array('extended' => '(EXTENDED(:?.CUT)?)'),
        array('hardcoded' => 'HC'),
        array('proper' => 'PROPER'),
        array('repack' => 'REPACK'),
        array('container' => '(MKV|AVI|MP4)'),
        array('widescreen' => 'WS'),
        array('website' => '^(\[ ?([^\]]+?) ?\])'),
        array('language' => '(rus\.eng|ita\.eng)'),
        array('sbs' => '(?:Half-)?SBS'),
        array('unrated' => 'UNRATED'),
        array('size' => '(\d+(?:\.\d+)?(?:GB|MB))'),
        array('3d' => '3D')
    );

    public $types = array(
        'season' => 'integer',
        'episode' => 'integer',
        'year' => 'integer',
        'extended' => 'boolean',
        'hardcoded' => 'boolean',
        'proper' => 'boolean',
        'repack' => 'boolean',
        'widescreen' => 'boolean',
        'unrated' => 'boolean',
        '3d' => 'boolean'
    );

    public function __construct() {}

    public function parse($name) {
        $this->parts = array();
        $this->torrent = array('name' => $name);
        $this->excess_raw = $name;
        $this->group_raw = '';
        $this->start = 0;
        $this->end = null;
        $this->title_raw = null;

        foreach($this->patterns as $patterns_single) {
            foreach($patterns_single as $key => $pattern) {
                if(!in_array($key, array('season', 'episode', 'website'))) {
                    $pattern = "\b{$pattern}\b";
                }

                $clean_name = str_replace('_', ' ', $this->torrent['name']);
                if(preg_match("/{$pattern}/i", $clean_name, $match) == 0) break;

                $index = array();
                if(is_array($match)) {
                    array_shift($match);
                }
                if(sizeof($match) == 0) break;
                if(sizeof($match) > 1) {
                    $index['raw'] = 0;
                    $index['clean'] = 1;
                }
                else {
                    $index['raw'] = 0;
                    $index['clean'] = 0;
                }

                if(isset($this->types[$key]) && $this->types[$key] == 'boolean') {
                    $clean = true;
                }
                else {
                    $clean = $match[$index['clean']];
                    if(isset($this->types[$key]) && $this->types[$key] == 'integer') {
                        $clean = (int)$clean;
                    }
                }

                if($key == 'group') {
                    if((isset($this->patterns[5][1]) && preg_match_all("/{$this->patterns[5][1]}/i", $clean)) ||
                            (isset($this->patterns[4][1]) && preg_match_all("/{$this->patterns[4][1]}/", $clean))) {
                        break;
                    }
                    if(preg_match('/[^ ]+ [^ ]+ .+/', $clean)) {
                        $key = 'episodeName';
                    }
                }
                if($key == 'episode') {
                    $sub_pattern = $this->escape_regex($match[$index['raw']]);
                    $this->torrent['map'] = preg_replace("/{$sub_pattern}/", '{episode}', $this->torrent['name']);
                }

                $this->part($key, $match, $match[$index['raw']], $clean);
            }
        }

        $raw = $this->torrent['name'];
        if(!is_null($this->end)) {
            $raw = explode('(', substr($raw, $this->start, $this->end - $this->start));
            $raw = $raw[0];
        }

        $clean = preg_replace("/^ -/", '', $raw);
        if(strpos($clean, ' ') === false && strpos($clean, '.') !== false) {
            $clean = str_replace('.', ' ', $clean);
        }
        $clean = str_replace('_', ' ', $clean);
        $clean = trim(preg_replace("/([\[\(_]|- )$/", '', $clean));

        $this->part('title', array(), $raw, $clean);

        $clean = preg_replace("/(^[-\. ()]+)|([-\. ]+$)/", '', $this->excess_raw);
        $clean = preg_replace("/[\(\)\/]/", ' ', $clean);
        $match = preg_split("/\.\.+| +/", $clean);
        if(sizeof($match) > 0 && is_array($match[0])) {
            $match = $match[0];
        }

        $clean = $match;
        $clean = array_filter($clean, function($var) {
            return $var != '-' ? true : false;
        });
        $clean = array_filter($clean, function($var) {
            return trim($var, '-');
        });
        $clean = array_values($clean);

        if(sizeof($clean) > 0) {
            $group_pattern = $clean[sizeof($clean) - 1] . $this->group_raw;
            if(strpos($this->torrent['name'], $group_pattern) == strlen($this->torrent['name']) - strlen($group_pattern)) {
                $this->late('group', array_pop($clean) . $this->group_raw);
            }

            if(isset($this->torrent['map']) && sizeof($clean) > 0) {
                $episode_name_pattern = '{episode}' . preg_replace("/_+$/", '', $clean[0]);

                if(strpos($this->torrent['map'], $episode_name_pattern) != -1) {
                    $this->late('episodeName', array_shift($clean));
                }
            }
        }

        if(sizeof($clean) != 0) {
            if(sizeof($clean) == 1) {
                $clean = $clean[0];
            }
            $this->part('excess', array(), $this->excess_raw, $clean);
        }
        return $this->parts;
    }

    private function escape_regex($subject) {
        return preg_replace("/[\-\[\]{}()*+?.,\\\^$|#\s]/", "\\\\$&", $subject);
    }

    private function part($name, $match, $raw, $clean) {
        # The main core instructuions
        $this->parts[$name] = $clean;

        if(sizeof($match) > 0) {
            # The instructions for extracting title
            $index = strpos($this->torrent['name'], $match[0]);
            if($index == 0) {
                $this->start = strlen($match[0]);
            }
            elseif(is_null($this->end) || $index < $this->end) {
                $this->end = $index;
            }
        }
        if($name != 'excess') {
            if($name == 'group') {
                $this->group_raw = $raw;
            }

            if(!is_null($raw)) {
                $this->excess_raw = str_replace($raw, '', $this->excess_raw);
            }
        }
    }

    private function late($name, $clean) {
        if($name == 'group') {
            $this->part($name, array(), null, $clean);
        }
        elseif($name == 'episodeName') {
            $clean = preg_replace("/[\._]/", ' ', $clean);
            $clean = preg_replace("/_+$/", '', $clean);
            $this->part($name, array(), null, trim($clean));
        }
    }
}
