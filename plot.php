#!/usr/bin/php
<?php
/* Written by Konrad Kosmatka, 2021 */
function mtscan_read_and_parse($filename)
{
    $compressed = stripos($filename, ".gz") === strlen($filename)-strlen(".gz");
    $content = file_get_contents($filename);
    if($compressed)
        $content = gzdecode($content);
    return json_decode($content, true);
}

if($argc < 4)
    die("usage: ".$argv[0]." <file> <address> <aggregation>\n");

$log = mtscan_read_and_parse($argv[1]);
$bssid = strtoupper(str_replace(":", "", $argv[2]));
$agg = $argv[3];

if(!isset($log[$bssid]))
    die("ERROR: BSSID not found in the log\n");
    
if(!isset($log[$bssid]['signals']))
    die("ERROR: RSSI samples not found in the log\n");

$length = 60*60*24;
$offset = $length / 2;

/* Data aggregation */
$data = [];
$y_min = null;
$y_max = null;

foreach($log[$bssid]['signals'] as $sample)
{
    $at = (int)($sample['t'] / $agg) * $agg;
    if(!isset($data[$at]))
        $data[$at] = [];
    
    $data[$at][] = $sample['s'];
    
    if($y_max == null || $sample['s'] > $y_max)
        $y_max = $sample['s'];
    if($y_min == null || $sample['s'] < $y_min)
        $y_min = $sample['s'];
}

ksort($data);

$fragments = [];
foreach($data as $t => $rssi)
{
    $key = (int)(($t - $offset) / $length) * $length;
    if(!isset($fragments[$key]))
        $fragments[$key] = [];
    $fragments[$key][$t] = $rssi;
}

foreach($fragments as $key => $fragment)
{
    $x_min = (int)($key / $length) * $length + $offset;
    $x_max = ((int)($key / $length) + 1) * $length + $offset;
    $output = $x_min.".png";
    
    //$x_min = array_key_first($fragment);
    //$x_max = array_key_last($fragment);

    /* Plot offsets */
    $left = 30;
    $right = 30;
    $top = 30;
    $bottom = 30;

    $step = 10;
    $steps = $y_max - $y_min;
    $tick_extent = 5;
    $y_ticks_every = 2;

    $width_plot = ($x_max - $x_min) / $agg + 1;
    $height_plot = $steps * $step;
    $width = $width_plot + $left + $right;
    $height = $height_plot + $top + $bottom;

    $font = 2;
    $font_width = imagefontwidth($font);
    $font_height = imagefontheight($font);

    /* ---------------- */
    $gd = imagecreatetruecolor($width, $height);

    $color = ["frame" => imagecolorallocatealpha($gd, 0xff, 0xff, 0xff, 32),
              "grid"  => imagecolorallocatealpha($gd, 0x80, 0x80, 0x80, 64),
              "text"  => imagecolorallocatealpha($gd, 0xff, 0xff, 0xff, 0),
              "plot"  => imagecolorallocatealpha($gd, 0x00, 0x00, 0xff, 0),
              "avail" => imagecolorallocatealpha($gd, 0x00, 0xff, 0x00, 0),
              "avg"   => imagecolorallocatealpha($gd, 0xff, 0xff, 0xff, 0),
              "bg"    => imagecolorallocatealpha($gd, 0x00, 0x00, 0x00, 0)];
              
    imageline($gd,
              $left-1,
              $top+0,
              $left-1,
              $top + $height_plot + 1,
              $color['frame']);

    imageline($gd,
              $left,
              $top + $height_plot + 1,
              $left + $width_plot,
              $top + $height_plot + 1,
              $color['frame']);
              
    imageline($gd,
              $width-$right,
              $top+0,
              $width-$right,
              $top + $height_plot + 1,
              $color['frame']);

    foreach ($fragment as $t => $rssi)
    {
        $line_x = ($t - $x_min) / $agg;
        $min_rssi = min($rssi);
        $max_rssi = max($rssi);
        $avg_rssi = array_sum($rssi)/count($rssi);
        
        $line_y = ($step * ($y_max - $max_rssi));
        $line_y2 = ($step * ($y_max - $min_rssi));
        
        imageline($gd,
                  $left + $line_x, 
                  $top + $line_y,
                  $left + $line_x,
                  $top + $line_y2,
                  $color['plot']);
                  
        $line_y = ($step * ($y_max - $avg_rssi));
        
        imagesetpixel($gd,
                      $left + $line_x, 
                      $top + $line_y,
                      $color['avg']);


        imageline($gd,
                  $left + $line_x, 
                  $top - 2,
                  $left + $line_x,
                  $top - 4,
                  $color['avail']);
                  
    }

    /* Grid */
    for($i = 0; $i <= $steps; $i += $y_ticks_every)
    {
        $line_y = $step * $i;
        
        imageline($gd,
                  $left - $tick_extent,
                  $top + $line_y,
                  $left + $width_plot + $tick_extent,
                  $top + $line_y,
                  $color['grid']);
        
        $text = (string)($y_max - $i);
        $text_x = $left - $tick_extent - 1 - $font_width * strlen($text);
        $text_y = $line_y - $font_height / 2;
        
        imagestring($gd,
                    $font,
                    $text_x,
                    $top + $text_y,
                    $text,
                    $color['text']);
                    
                    
        $text_x = $width - $right + $tick_extent + 2;
        
        imagestring($gd,
                    $font,
                    $text_x,
                    $top + $text_y,
                    $text,
                    $color['text']);
    }

    /* xticks (hours) */
    for($t = (int)($x_min / (60*60)) * (60*60); $t <= $x_max; $t += 60*60)
    {
        $line_x = ($t - $x_min) / $agg;
        $line_y = $height_plot + $tick_extent;
        
        imageline($gd,
                  $left + $line_x,
                  $top + 0,
                  $left + $line_x,
                  $top + $line_y,
                  $color['grid']);
        
        $text = date("H", $t);
        $text_x = $line_x - $font_width;
        $text_y = $line_y;
        
        imagestring($gd,
                    $font,
                    $left + $text_x,
                    $top + $text_y,
                    $text,
                    $color['text']);
    }

    /* xticks (days) */
    for($t = ((int)($x_min / $length) + 1) * $length; $t <= $x_max; $t += $length)
    {
        $line_x = ($t - $x_min) / $agg;
        $line_y = $height_plot + 15;
        
        $text = date("Y-m-d", $t);
        $text_x = $line_x - $font_width * strlen($text) / 2;
        $text_y = $line_y;
        
        imagestring($gd,
                    $font,
                    $left + $text_x,
                    $top + $text_y,
                    $text,
                    $color['text']);
    }

    imagepng($gd, $output);
}
?>
