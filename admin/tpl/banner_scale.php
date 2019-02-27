<?php
/* ###VERSIONSBLOCKINLCUDE### */


  if ($h = $_GET['x'])
    $fl_swap = true;
  else
    $h = $_GET['y'];
  $w = 6*ceil(log10($h+1));

  $im = imagecreate(5+$w, $h);
  $fg = imagecolorallocate($im,   0,   0,   0);
  $bg = imagecolorallocate($im, 225, 225, 225);
  imagefilledrectangle ($im, 0, 0, $w+4, $h-1, $bg);
  for($y=5; $y<$h; $y+=5)
  {
    $yp = ($fl_swap ? $h-$y : $y);
    if (!($y%50))
    {
      imageline($im, $w-2, $yp, $w+5, $yp, $fg);
      imagestring($im, 1, $w-6*ceil(log10($y+1)), $yp-3, "$y", $fg);
    }
    elseif ($y%10)
      imageline($im, $w+2, $yp, $w+5, $yp, $fg);
    else
      imageline($im, $w, $yp, $w+5, $yp, $fg);
  }
  if ($fl_swap)
    $im = imagerotate($im, -90, $bg);
  header("Content-type: image/png");
  imagepng($im);
?>