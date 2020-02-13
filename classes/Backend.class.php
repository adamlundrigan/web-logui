<?php

class Backend
{
  /// Is the backend valid, eg. does it have configured sources?
  /// (Note: This does NOT check if said sources are actually reachable)
  public function isValid() { return true; }

  /// Does the backend support loading history?
  public function supportsHistory() { return false; }

  /// Does the backend support loading the queue?
  public function supportsQueue() { return false; }

  /// Does the backend support loading quarantines?
  public function supportsQuarantine() { return false; }

  /// Loads the mail history
  public function loadMailHistory($search, $size, $param, &$errors = array()) { return null; }

  /// Loads the mail queue
  public function loadMailQueue($search, $size, $param, &$errors = array()) { return null; }
}
