<?php

/**
 * レコメンドエンジン
 *
 * Plugin Name: Memory filtering
 * @copyright  Copyright (c) 2016 Yuta Seto
 * @license    Sent license
 * @version    1.0
 */

class MemoryFiltering {

  public function __construct(){
    add_filter('query_vars', array($this, 'my_add_twitter_to_qvar'));
    add_action('init', array($this, 'custom_rewrite_basic'));
    add_action('wp', array($this, 'init'));
    add_action('admin_menu', array($this, 'add_pages'));
  }
  
  public function add_pages(){
    add_menu_page('レコメンド', 'MemoryFiltering', 8, __FILE__, array($this, 'r_top_page'));
  }

  public function r_top_page() {
    echo '<div><h2>MemoryFiltering</h2></div>';
  }

  public function my_add_twitter_to_qvar( $vars ) {
    $vars[] = 'api';
    $vars[] = 'ver';
    return $vars;
  }

  public function custom_rewrite_basic() {
    add_rewrite_rule('api/([0-9]+)/([a-zA-Z0-9_]+)/?$', 'index.php?ver=$matches[1]&api=$matches[2]', 'top');
  }

  public function api_request_check(){
    return !is_admin() && get_query_var('api') && get_query_var('ver');
  }

  public function init(){

    if(!$this->api_request_check()){
      return;
    }

    $version = get_query_var('ver');
    $action = 'action_' . get_query_var('api');

    $results = $this->$action();

    echo json_encode($results);
    exit;
  }

  public function action_reccomend(){
    global $wpdb;

    $wpdb->insert( 
      'wp_recommend_scores', 
      array( 
        'user_id' => $_COOKIE['user_id'], 
        'type' => $_COOKIE['type'], 
        'item_id' => $_POST['item_id'],
        'score' => $_POST['score'],
        'found' => isset($_POST['found']) ? $_POST['found'] : 0
      ), 
      array( 
        '%s', 
        '%s', 
        '%s', 
        '%s', 
        '%s' 
      ) 
    );

    return [];
  }

  public function action_high_score_articles(){
    global $wpdb;
    
    $sql = $wpdb->prepare("SELECT * FROM wp_recommend_scores WHERE user_id = %s ORDER BY score DESC limit 10", $_COOKIE['user_id']);
    $exec = $wpdb->get_results($sql);

    return $exec;
  }

  private function get_data($my_id, $type = 0){
    global $wpdb;
      
    $sql = $wpdb->prepare("SELECT * FROM wp_recommend_scores GROUP BY user_id");
    $users = $wpdb->get_results($sql);

    $data = [];
    foreach($users as $user){
      $score = [];

      $score_sql = $wpdb->prepare("SELECT * FROM wp_recommend_scores where user_id = %s", $user->user_id);
      $scores = $wpdb->get_results($score_sql);

      foreach($scores as $s){
        $score[$s->item_id] = $s->score;
      }

      $data[$user->user_id] = $score;
    }

    return $data;    
  }

  private function get_rec_data($type = 0){
    global $wpdb;

    $sql = $wpdb->prepare("SELECT *, (SELECT COUNT(*) FROM wp_recommend_counts as wpr WHERE wpr.type = wr.type AND wpr.item_id = wr.item_id) as count FROM wp_recommend_counts as wr WHERE type = %s GROUP BY item_id", $type);
    $recommends = $wpdb->get_results($sql);


    $data = [];
    foreach($recommends as $recommend){
      $data[$recommend->item_id] = $recommend->count;
    }

    return $data;
  }

  public function action_collaborative_filtering_articles(){
    global $wpdb;

    require_once('score.php');

    $my_id = $_COOKIE['user_id'];
    $data = $this->get_data($my_id);
    $rec_data = $this->get_rec_data();
    $exec = getCollaborativeFiltering($data, $rec_data, $my_id);

    $results = array_slice(array_keys($exec), 0, 10);

    foreach($results as $result){
      $wpdb->insert( 
        'wp_recommend_counts', 
        array( 
          'type' => 2, 
          'item_id' => $result
        ), 
        array( 
          '%s', 
          '%s' 
        ) 
      );
    }

    return $results;
  }

  public function action_super_collaborative_filtering_articles(){
    global $wpdb;
    require_once('score.php');

    $my_id = $_COOKIE['user_id'];
    $data = $this->get_data($my_id);
    $rec_data = $this->get_rec_data();
    $exec = getSuperCollaborativeFiltering($data, $rec_data, $my_id);

    $results = array_slice(array_keys($exec), 0, 10);

    foreach($results as $result){
      $wpdb->insert( 
        'wp_recommend_counts', 
        array( 
          'type' => 3, 
          'item_id' => $result
        ), 
        array( 
          '%s', 
          '%s' 
        ) 
      );
    }

    return $results;
  }

  public function action_reccomend_count(){
    global $wpdb;

    $wpdb->insert( 
      'wp_recommend_count', 
      array( 
        'type' => $_COOKIE['type'], 
        'item_id' => $_POST['item_id']
      ), 
      array( 
        '%s', 
        '%s' 
      ) 
    );

    return [];
  }

  public function action_get_articles(){
    $posts = get_posts('orderby=rand&numberposts=20');
    $results = [];
    foreach($posts as $post){
      $results[] = $post->ID;
    }
    return $results;
  }
  
  private function redirect_404(){
    global $wp_query;
    $wp_query->is_404 = true;
  }
}

$api = new MemoryFiltering();
