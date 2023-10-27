<?php

/**
 * TextformatterYouTubeConsent
 * @author Ivan Milincic <hello@kreativan.dev>
 * @link http://www.kraetivan.dev
 */

namespace ProcessWire;

class TextformatterYouTubeConsent extends Textformatter implements ConfigurableModule {

  public $formater;

  public static function getModuleInfo() {
    return array(
      'title' => 'YouTube Consent with TextformatterVideoEmbed',
      'version' => 100,
      'summary' => 'Display youtube consent message before embedding video with TextformatterVideoEmbed',
      'author' => 'Ivan Milincic',
      'href' => 'https://github.com/kreativan/TextformatterYouTubeConsent',
      'requires' => ['TextformatterVideoEmbed'],
    );
  }

  public function __construct() {
    parent::__construct();
    $this->formater = $this->modules->get('TextformatterVideoEmbed');
  }

  /**
   * Format
   * Check for youtube links using simple strpos()
   * If there is youtube videos, replace each youtube link with consent html markup.
   * When visitor accept the consent, we set a session variable insead of cookie so we dont neeed cookie consent.
   * If visitor is accepted the conest, we just run TextformatterVideoEmbed
   */
  public function format(&$value) {

    // check if there is a video in content
    $youtube = strpos($value, 'youtu') !== false;
    if (!$youtube) return;

    /**
     * When accept, store session variable
     * So we dont ask for consent all the time.
     * Not going to use cookies, because we dont need cookie consent.
     */
    if ($this->input->get->youtube_consent) {
      $this->session->set('youtube_consent', true);
    }

    // Check if user has accepted youtube video before
    $is_youtube_consent = $this->session->get('youtube_consent');

    /**
     * If user has accepted youtube video before, format it with TextformatterVideoEmbed
     * if not, replace youtube video with consent html
     */
    if ($is_youtube_consent) {
      $this->formater->format($value);
    } else {
      $videos = $this->find_youtube_videos($value);
      foreach ($videos as $video) {
        $value = str_replace($video, $this->cookie_html($video), $value);
      }
      return $value;
    }
  }

  /**
   * Find all youtube links in content
   * @return array
   */
  public function find_youtube_videos($value) {
    $regex =
      '#' .
      '<(?:p|h[1-6])' . // open tag <p or <h1, <h2, etc.
      '(?:>|\s+[^>]+>)\s*' . // rest of open tag and close bracket
      '(' . // capture #1: full URL 
      'https?://(?:www\.)?youtu(?:\.be|be\.com)+/' . // scheme + host "https://youtu.be/"
      '(?:watch/?\?v=|v/)?' . // optional "watch?v=" or "v/"
      '([^\s&<\'"]+)' . // capture #2: video ID (U&LC letters, numbers)
      ')' . // end of capture #1: full URL
      '((?:&|&amp;|\?)[-_,.=&;a-zA-Z0-9]*)?.*?' . // capture #3: optional query string
      '</[ph123456]+>' . // close tag
      '#';
    preg_match_all($regex, $value, $matches);
    if (empty($matches[0])) return;
    $videos = [];
    foreach ($matches as $match) {
      if (substr($match[0], 0, 8) == "https://") {
        $videos[] = $match[0];
      }
    }
    return $videos;
  }

  /**
   * Get youtube video poster based on the url
   * @param string $url
   * @return string
   */
  public function get_youtube_video_poster($url) {
    $video_id = '';
    $regex = '#(?:https?://)?(?:www\.)?(?:youtu\.be/|youtube\.com/(?:watch(?:\?v=|/)|embed/|v/))([\w-]{10,12})#x';
    if (preg_match($regex, $url, $matches)) {
      $video_id = $matches[1];
    }
    if ($video_id) {
      return "https://img.youtube.com/vi/$video_id/maxresdefault.jpg";
    }
    return null;
  }

  /**
   * Consent html markup
   * Display youtube video poster and a message.
   * We get poster image from get_youtube_video_poster() method
   * @param string $video_url
   * @return string
   */
  public function cookie_html($video_url) {
    $headline = __('YouTube Video');
    $link_text = __('Accept');
    $text = __('Before playing the video, we need your consent for data processing. By clicking Accept, you agree to YouTube Terms of Service and acknowledge their data collection practices.');
    $html = "<div class='youtube-embed-consent' style='position: relative;'>";
    $html .= "<img src='{$this->get_youtube_video_poster($video_url)}' />";
    $html .= "<div class='youtube-embed-consent-message' style='position: absolute;top:0px;left:0px;right:0px;bottom:0px;box-sizing: border-box;padding: 50px;background: rgba(255, 255, 255, 0.8);'>";
    $html .= "<h3>$headline</h3>";
    $html .= "<p>$text</p>";
    $html .= "<a href='./?youtube_consent=1'>{$link_text}</a>";
    $html .= "</div>";
    $html .= "</div>";
    return $html;
  }
}
