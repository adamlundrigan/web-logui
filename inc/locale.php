<?php

function preferred_language ($languages, $accept)
{
  // HTTP_ACCEPT_LANGUAGE is defined in
  // http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html#sec14.4
  // pattern to find is therefore something like this:
  //    1#( language-range [ ";" "q" "=" qvalue ] )
  // where:
  //    language-range  = ( ( 1*8ALPHA *( "-" 1*8ALPHA ) ) | "*" )
  //    qvalue         = ( "0" [ "." 0*3DIGIT ] )
  //            | ( "1" [ "." 0*3("0") ] )
  preg_match_all("/([[:alpha:]]{1,8})(-([[:alpha:]|-]{1,8}))?" .
           "(\s*;\s*q\s*=\s*(1\.0{0,3}|0\.\d{0,3}))?\s*(,|$)/i",
           $accept, $hits, PREG_SET_ORDER);

  // default language (in case of no hits) is the first in the array
  $bestlang = $languages[0];
  $bestqval = 0;

  foreach ($hits as $arr) {
    // read data from the array of this hit
    $langprefix = strtolower ($arr[1]);
    if (!empty($arr[3])) {
      $langrange = strtolower ($arr[3]);
      $language = $langprefix . "-" . $langrange;
    }
    else $language = $langprefix;
    $qvalue = 1.0;
    if (!empty($arr[5])) $qvalue = floatval($arr[5]);

    // find q-maximal language
    if (in_array($language,$languages) && ($qvalue > $bestqval)) {
      $bestlang = $language;
      $bestqval = $qvalue;
    }
    // if no direct hit, try the prefix only but decrease q-value
    // by 10% (as http_negotiate_language does)
    else if (in_array($langprefix,$languages)
      && (($qvalue*0.9) > $bestqval))
    {
      $bestlang = $langprefix;
      $bestqval = $qvalue*0.9;
    }
  }
  return $bestlang;
}

$languages = array_map(function ($f) {
  return str_replace('.yaml', '', str_replace('_', '-', strtolower(basename($f))));
}, glob(BASE.'/locale/*.yaml'));

unset($languages[array_search('en-us', $languages)]);
$languages = array_merge(['en-us'], $languages);
$preferred_language = preferred_language($languages, $_SERVER['HTTP_ACCEPT_LANGUAGE']);
$preferred_language = explode('-', $preferred_language);
$preferred_language = $preferred_language[0] . '_' . strtoupper($preferred_language[1]);
putenv('LC_ALL='.$preferred_language.'.UTF-8');
setlocale(LC_ALL, $preferred_language.'.UTF-8');
