<?php

if (!function_exists('str_ends_with')) {
  function str_ends_with($haystack, $needle)
  {
    return substr($haystack, -strlen($needle)) === $needle;
  }
}


if (!function_exists('str_contains')) {
  function str_contains($haystack, $needle)
  {
    return strpos($haystack, $needle) !== false;
  }
}
