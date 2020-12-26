<?php

require_once __DIR__ . '/vendor/autoload.php';

use Nihilarr\PTN;
use Matriphe\ISO639;
use Captioning\Format\SubripFile;
use Captioning\Format\WebvttFile;

define('MOVIEDB_BASE_URL', 'https://api.themoviedb.org/3');
define('MOVIEDB_IMG_URL', 'https://image.tmdb.org/t/p/w500');

$_SERVER = array(
    'SERVER_ADDR' => shell_exec("hostname -I | awk '{print $1}'"),
    'SERVER_NAME' => 'localhost',
    'SERVER_PORT' => '8080',
);

class Content {

    //Security options
    private $allow_delete = true; // Set to false to disable delete button and delete POST request.
    private $allow_upload = true; // Set to true to allow upload files
    private $allow_create_folder = true; // Set to false to disable folder creation
    private $allow_direct_link = true; // Set to false to only allow downloads and not direct link
    private $allow_show_folders = true; // Set to false to hide all subdirectories

    private $path = '/home/jon/Videos/';
    private $media_icon;
    private $apiKey;

    private $media_icon_ext = ['movie', 'tv', 'images', 'pictures'];
    private $hidden_Entries = ['$RECYCLE.BIN','System Volume Information','msx'];
    private $video_ext = ['mp4','mpg','mpeg','vob','avi','mkv'];
    private $audio_ext = ['mp3','audio'];
    private $image_ext = ['gif','jpg','jpeg','png','ico'];
    private $subfiles_ext = ['vtt', 'srt'];

    public function __construct() {
        $this->apiKey = $this->get_api_key();
    }

    /**
     * Browses on $media_catagory and creates a $data_obj
     * 
     * @param $media_catagory, $dir
     * 
     * @return $data_obj
     */
    function create_data_object($media_catagory, $dir) {
        $data_obj = array(
            'type' => 'list',
            'template' => array(
                'type' => 'separate',
                'layout' => '0,0,2,4'
            )
        );
        if ($media_catagory === "movie") {
            $movie_items = array();
            $data_obj['items'] = $this->create_media_items($dir, $movie_items);
        } else if ($media_catagory === "tv") {
            $data_obj['pages'] = $this->create_tv_pages($dir);
        }

        return $data_obj;
    }

    /**
     * Recursive func. that digs down through every sub-folder and
     * returns a media item when media file is found.
     *
     * @param $dir, &$media_items
     * 
     * @return &$media_items
     */
    function create_media_items($dir, &$media_items) {
        $ptn = new PTN();
        $files_and_folders = $this->get_directories($dir);

        // prevent empty ordered elements
        if (count($files_and_folders) < 1) return;

        foreach($files_and_folders as $ff) {
            if (is_dir($dir.'/'.$ff)) {
                $folder = $ff.'/'.$ff;
                $this->create_media_items($dir.'/'.$ff, $media_items);
                $folder = "";
            }

            foreach($this->video_ext as $ext) {
                if (stripos($ff, $ext, -3) !== false) {
                    $item = $this->media_items($this->media_icon, $ptn->parse($ff));
                    $item['action'] = "video:plugin:http://msx.benzac.de/plugins/html5x.html?url=".$this->action_url_encoded(str_replace($this->path, "", '/'.$dir), $ff);
                    //TODO: need to fix this if sentence
                    if ($dir !== "/home/jon/Videos/Movies") {
                        $item['properties'] = $this->subtitles_properties($dir);
                    }
                    $media_items[] = $item;
                }
            }	
        }

        return $media_items;
    }

    /**
     * Collect raw subtitles map where folder as key.
     * loop over raw subtitles and if '.srt' file with no corresponding '.vtt' file
     * we convert the '.srt' file to '.vtt' format understandable by our player.
     * Gather all subtitles distinct by language.
     * 
     * @param $dir
     * 
     * @return $object
     */
    function subtitles_properties($dir) {
        $object = new stdClass();
        $subtitles_raw = $this->rec_subtitles($dir);

        foreach ($subtitles_raw as $key => $subtitles) {
            if (is_array($subtitles) || is_object($subtitles)) {
                foreach ($subtitles as $subtitle) {
                    $same_file = false;
                    if (stripos($subtitle, 'srt', -3) !== false) {
                        // if '.srt' found, look if corresponding '.vtt' file exists,
                        // if so, we do not convert the '.srt' file to '.vtt'.
                        foreach ($subtitles as $subtitle_again) {
                            if (stripos($subtitle_again, 'vtt', -3) !== false) {
                                if (substr($subtitle, 0, -4) === substr($subtitle_again, 0, -4)) {
                                    $this->subtitle_entry($subtitle_again, $object);
                                    $same_file = true;
                                    break;
                                }
                            }
                        }

                        if (!$same_file) {
                            $this->subtitle_entry($subtitle, $object);
                        }
                    }
                }
                $object->{"button:content:icon"} = "subtitles";
                $object->{"button:content:action"} = "panel:request:player:subtitle";
            }
        }

        return $object;
    }

    /**
     * Recursive func. that iterates through all folders and subfolders and gathers
     * all subtitle files for corresponding media file.
     * And return them as a map, where the folder is the key.
     * 
     * @param $dir, $subtitles_raw
     * 
     * @return $subtitle_map
     */
    function rec_subtitles($dir, &$subtitles_raw = null) {
        $files_and_folders = $this->get_directories($dir);

        // prevent empty ordered elements
        if (count($files_and_folders) < 1) return;

        $subtitle_map = array();
        foreach($files_and_folders as $ff) {
            if (is_dir($dir.'/'.$ff)) {
                $this->rec_subtitles($dir.'/'.$ff, $subtitles_raw);
            }

            foreach ($this->subfiles_ext as $sub_ext) {
                if (stripos($ff, $sub_ext, -3) !== false) {
                    $subtitles_raw[] = $dir.'/'.$ff;
                } 
            }

            $subtitle_map[$dir] = $subtitles_raw;
        }

        return $subtitle_map;
    }

    /**
     * First gather all same tv-shows episodes unrelated to folders, then create $tv_show_seasons obj.
     * based on tv-show name.
     */
    function create_tv_pages($dir) {
        $ptn = new PTN();
        
        $files_and_folders = $this->get_directories($dir);

        $tv_show_seasons = array();
        foreach ($files_and_folders as $files) {
            $parsed_tv_show_title = $ptn->parse($files)['title'];
            $clean = trim(preg_replace("/season/i", '', $parsed_tv_show_title));
            $tv_show_seasons[$clean][] = array($dir.'/'.$files);
        }

        $tv_pages = array();
        foreach ($tv_show_seasons as $tv_show_name => $all_episodes) {
            $tv_show_obj = $this->get_tv_show_obj($tv_show_name);
            $tv_pages[] = array(
                'headline' => $tv_show_obj['original_name'],
                'items' => array(array(
                    'type' => 'separate',
                    'layout' => '0,0,2,4',
                    'image' => MOVIEDB_IMG_URL.$tv_show_obj['poster_path'],
                    'action' => 'panel:data',
                    'data' => array(
                        'pages' => array(array(
                            'items' => $this->tv_show_seasons($all_episodes, $tv_show_obj),
                        ))
                    )
                ))
            );
        }

        return $tv_pages;
    }

    /**
     * Based on var $media_catagory type a media item record is created.
     * 
     * @param $media_catagory, $parsed_torrent
     * 
     * @return a media item
     */
    function media_items($media_catagory, $parsed_torrent) {    
        if ($media_catagory === "tv") {
            return [
                'title' => $parsed_torrent['excess'] ?? '',
                'tag' => sprintf("E%u", $parsed_torrent['episode'] ?? ''),
                'tagColor' => 'msx-yellow',
                "badge" => sprintf("{txt:msx-white:Season %u}", $parsed_torrent['season'] ?? ''),
                "badgeColor" => "#643fa6",
                'playerLabel' => sprintf('S%uE%u', $parsed_torrent['season'] ?? '', $parsed_torrent['episode'] ?? ''),
                'tmpSeason' => $parsed_torrent['season'] ?? '',
                'tmpEpisode' => $parsed_torrent['episode'] ?? '',
            ];
        } else {
            $movie_item = $this->get_movie_item($parsed_torrent);

            return [
                'title' => $movie_item[0],
                'image' => $movie_item[1],
                'tag' => "{ico:star}".$movie_item[2],
                'tagColor' => 'msx-yellow',
                'playerLabel' => $movie_item[0]
            ];
        }
    }

    /**
     * First the whole tv-show media items gets created, then we group
     * tv-shows by there seasons. Then we call tv_show_season_items()
     * 
     * @param $ff, $tv_show_obj
     * 
     * @return $tv_seasons
     */
    function tv_show_seasons($ffs, $tv_show_obj) {    
        $items = array();
        foreach ($ffs as $ff) {
            $this->create_media_items($ff[0], $items);
        }
        $local_tv_obj_group_by_season = $this->group_by('tmpSeason', $items);
        
        $tv_seasons = array();
        $layout = 0;
        $season_poster_path = "";
        foreach ($local_tv_obj_group_by_season as $season_number => $local_tv_episodes) {
            foreach ($tv_show_obj['seasons'] as $season) {
                if ($season['season_number'] === $season_number) {
                    $season_poster_path = $season['poster_path'];
                }
            }

            $tv_seasons[] = array(
                "type" => "separate",
                "layout" => sprintf("%u,0,2,4", $layout),
                'image' => MOVIEDB_IMG_URL.$season_poster_path,
                "action" => "panel:data",
                "data" => array(
                    "headline" => $tv_show_obj['name'],
                    "template" => array(
                        "type" => "separate",
                        "layout" => "0,0,2,4",
                        "color" => "msx-glass",
                        "iconSize" => "medium",
                        "title" => "Title",
                    ),
                    "items" => $this->tv_show_season_items($local_tv_episodes, $tv_show_obj['name'], $tv_show_obj['id'], $season_number, $season_poster_path),
                ),
            );
            $layout += 3;
        }

        return $tv_seasons;
    }

    /**
     * First fetch then whole tv-show season from external db, then we loop through
     * the local tw-show episode and match it with the one from the exeternal db.
     * If episode in not found in external db, we created anywhay with lesser data.
     * 
     * @param $local_tv_episodes, $tv_show_id, $season_number, $season_poster_path
     * 
     * @return $season_items
     */
    function tv_show_season_items($local_tv_episodes, $tv_show_name, $tv_show_id, $season_number, $season_poster_path) {
        $season_items = array();
        $tv_show_season_obj = $this->get_tv_show_season($tv_show_id, $season_number);
        foreach ($local_tv_episodes as $local_episode) {
            $found_episode = false;
            foreach ($tv_show_season_obj['episodes'] as $episode) {
                if ($local_episode['tmpEpisode'] === $episode['episode_number']) {
                    $found_episode = true;
                    $season_items[] = [
                        'title' => $episode['name'],
                        'image' => MOVIEDB_IMG_URL.$episode['still_path'],
                        'tag' => "E".$episode['episode_number'],
                        'tagColor' => 'msx-yellow',
                        "badge" => sprintf("{txt:msx-white:Season %d}", $episode['season_number']),
                        "badgeColor" => "#643fa6",
                        'playerLabel' => $tv_show_name.' | S'.$episode['season_number'].'E'.$episode['episode_number'].' | '.$episode['name'],
                        'action' => $local_episode['action'],
                    ];
                    break;
                }
            }
            if (!$found_episode) {
                $local_episode['image'] = MOVIEDB_IMG_URL.$season_poster_path;
                $local_episode['playerLabel'] = sprintf("%s %s", $local_episode['playerLabel'], $local_episode['title']);
                $season_items[] = $local_episode;
            }
        }

        return $season_items;
    }

    /**
     * Convert a '.srt' file via third party library to format that our player understands '.vtt'
     * Then create the subtitle object per each language.
     * 
     * @param $subfile_file, $dir, $object
     * 
     * @return $object
     */
    function subtitle_entry($subtitle_file, &$object) {
        $iso639 = new Matriphe\ISO639\ISO639;

        $search_str = strtolower(substr($subtitle_file, strripos($subtitle_file, '.', -5)));
        $file_ext = substr($subtitle_file, -4);

        foreach ($iso639->allLanguages() as $languages) {
            if ($this->find_correct_language($search_str, $languages)) {
                $object->{sprintf("html5x:subtitle:%s:%s", $languages[0], $languages[4])} = $this->action_url_encoded("", str_replace($this->path, "", $subtitle_file));
                return;
            }
        }
        // odd sub file name, assume it's English
        $object->{"html5x:subtitle:en:English"} = $this->action_url_encoded("", str_replace($this->path, "", $subtitle_file));
    }

    function find_correct_language($search_str, $languages) {
        for ($i=4; $i > 0; $i -= 2) { 
            if (strpos($search_str, strtolower($languages[$i])) !== false) {
                $clean = substr($search_str, 0, strlen($search_str)-4);
                $clean = trim(preg_replace("/^[^_]*|".$languages[$i]."|_/", ' ', $clean));
                if ($clean === "" || $clean === strtolower($languages[4])) {
                    $clean = $languages[4];
                } else {
                    if ($clean[0] !== "[") $clean = "[".$clean."]";
                }
                return true;
            }
        }

        return false;
    }

    function get_directories($dir) {
        $files_and_folders = scandir($dir);
        unset($files_and_folders[array_search('.', $files_and_folders, true)]);
        unset($files_and_folders[array_search('..', $files_and_folders, true)]);

        return $files_and_folders;
    }

    private function get_api_key() {
        $file_content = file_get_contents("./msx/start.json");
        $json = json_decode($file_content);

        return $json->tmdb_api_key;
    }

    function get_media_icon($folder) {
        foreach($this->media_icon_ext as $icon_ext) {
            if (strpos(strtolower($folder), $icon_ext) !== FALSE)
                return $icon_ext;
        }
    }

    function get_tv_show_obj($tv_show_name) {
        $tv_show_id = $this->call_api('GET', MOVIEDB_BASE_URL.'/search/tv', array(
            'api_key' => $this->apiKey,
            'query' => $tv_show_name,
            'include_adult' => 'false',
        ));

        $tv_show_obj = $this->call_api('GET', MOVIEDB_BASE_URL.'/tv/'.$tv_show_id['results'][0]['id'], array(
            'api_key' => $this->apiKey,
        ));

        return $tv_show_obj;
    }

    function get_tv_show_season($tv_show_id, $tv_season_number) {
        return $this->call_api('GET', MOVIEDB_BASE_URL.'/tv/'.$tv_show_id.'/season/'.$tv_season_number, array(
            'api_key' => $this->apiKey
        ));
    }

    function get_movie_item($parsed_torrent) {        
        $movie_obj = $this->call_api('GET', MOVIEDB_BASE_URL.'/search/movie', array(
            'api_key' => $this->apiKey,
            'query' => $parsed_torrent['title'],
            'year' => $parsed_torrent['year'],
            'include_adult' => 'false'
        ));
        $result_obj = $movie_obj['results'][0];

        return array($result_obj['title'], MOVIEDB_IMG_URL.$result_obj['poster_path'], $result_obj['vote_average']);
    }

    function get_file_type($entry){
        $ext = strtolower(pathinfo($entry, PATHINFO_EXTENSION));
        if(in_array($ext,$this->video_ext)){
            return "video";
        }
        if(in_array($ext,$this->audio_ext)){
            return "audio";
        }
        if(in_array($ext,$this->image_ext)){
            return "image";
        }
        return "other";
    }

    function action_url_encoded($directory, $entry){
        $file_type = $this->get_file_type($entry);
        $icon_Mapping = array(
            "video"=>'video',
            "audio"=>'audio',
            "image"=>'image',
            "other"=>'link'
        );

        return 'http://' . $_SERVER['SERVER_ADDR'] . ':' . $_SERVER['SERVER_PORT'] . $this->encode_url($directory, '/'.$entry);
    }

    function encode_url($title, $action) {
        $urlEncoded = ($title ? rawurlencode($title) : '').rawurlencode($action);
        $urlEncoded = str_replace('%28', '(', $urlEncoded);
        $urlEncoded = str_replace('%29', ')', $urlEncoded);
        $urlEncoded = str_replace('%2F', '/', $urlEncoded);
        $urlEncoded = str_replace('%27', "'", $urlEncoded);
        
        return $urlEncoded;
    }

    function group_by($key, $data) {
        $result = array();
        foreach ($data as $element) {
            $result[$element[$key]][] = $element;
        }

        return $result;
    }

    function call_api($method, $url, $data = false) {
        $curl = curl_init();
        if (!$curl) {
            die("Couldn't initialize a cURL handle");
        }

        switch ($method)
        {
            case "POST":
                curl_setopt($curl, CURLOPT_POST, 1);

                if ($data)
                    curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
                break;
            case "PUT":
                curl_setopt($curl, CURLOPT_PUT, 1);
                break;
            default:
                if ($data)
                    $url = sprintf("%s?%s", $url, http_build_query($data));
        }

        // Optional Authentication:
        curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($curl, CURLOPT_USERPWD, "username:password");
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);

        $result = curl_exec($curl);
        $json_result = json_decode($result, true);
               
        if (isset($json_result['status_message'])) {
            if ($json_result['status_message'] === "Invalid API key: You must be granted a valid key.") {
                echo $json_result['status_message']." | provided api key: ".$this->apiKey."\n";
                exit(1);
            }
        }
        
        return $json_result;
    }

    function create_menu_object($dir) {
        $files_and_folders = $this->get_directories($dir);

        // prevent empty ordered elements
        if (count($files_and_folders) < 1) return;

        $menu_array = array();
        foreach ($files_and_folders as $ff) {
            $this->media_icon = $this->get_media_icon($ff);
            if (!isset($this->media_icon)) continue; // if media_icon matches folder we continue.
            $menu_array[] = [
                'icon' => $this->media_icon,
                'label' => $ff,
                'data' => $this->create_data_object($this->media_icon, $dir.$ff),
            ];
        }

        return $menu_array;
    }

    public function run() {
        $responseObj = new stdClass();
        $responseObj->headline = "Home Media Server";
        $responseObj->menu = $this->create_menu_object($this->path);

        $fp = fopen('menu.json', 'w');
        fwrite($fp, json_encode($responseObj, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
        fclose($fp);
    }
}

$p = new Content();
$p->run();

?>