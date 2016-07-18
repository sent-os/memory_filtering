<?php

class Score {
  public $itemName;
  public $score;

  function Score($itemName, $score) {
    $this->itemName = $itemName;
    $this->score = $score;
  }
}

/**
 * 類似度を計測するメソッド。
 * 
 * @param prefs 分析対象データ
 * @param person1 対象者1
 * @param person2 対象者2
 * @return 類似度
 */
function simDistance($prefs, $person1, $person2) {

  // ユークリッド距離で判定する
  
  // 2人とも評価しているアイテムリストを生成する
  $movieList = [];
  $user1MovieList = $prefs[$person1];
  $user2MovieList = $prefs[$person2];
  foreach($user1MovieList as $key => $movie) {
    if (isset($user2MovieList[$key])){
      $movieList[] = $key;
    }
  }

  // もし２人に共通のアイテムが無ければ、類似性なしと判断する
  if (count($movieList) === 0) {
    return 0.0;
  }
  
  // 全ての差の平方根を足し合わせる
  // $user1Map = rawDataMap.get(person1);
  // $user2Map = rawDataMap.get(person2);
  $sumOfSquares = 0.0;
  foreach($movieList as $movie){
    $sumOfSquares += pow($user1MovieList[$movie] - $user2MovieList[$movie], 2);
    // echo $user1MovieList[$movie] . " : " . $user2MovieList[$movie] . " => " . pow($user1MovieList[$movie] - $user2MovieList[$movie], 2) . "\n";
  }
  // 数値が大きいほど類似性が高いことにしたいので、逆数を取る。
  // その際にゼロ除算しないように+1する。
  // return 1 / (1 + $sumOfSquares);
  // return $sumOfSquares;
  return 1 / (1 + sqrt($sumOfSquares));
}

function getusers($data, $my_id){
  // 全てのユーザの類似度
  $ratings = [];
  // 全てのユーザで類似度を計算する
  foreach(array_keys($data) as $user){
    // 自分の場合は計算しない
    if($my_id === $user){
      continue;
    }
    $sim = simDistance($data, $my_id, $user);
    if($sim > 0){
      $ratings[$user] = $sim;
    }
  }
  // 高い順
  arsort($ratings);
  return $ratings; 
}


function getRecommendations($prefs, $user) {
  // 類似度で重み付けした映画評価の合計
  $totals = [];

  // 類似度の合計
  $simSums = [];
  
  // 評価者を１人ずつ
  foreach(array_keys($prefs) as $other) {
    
    // 自分同士はスキップ
    if ($user === $other) continue;
    
    // 自分との類似度を計算する
    // simDistanceは以前のブログで紹介した類似度計測を利用します
    $sim = simDistance($prefs, $user, $other);

    // まだ見ていないアイテムのみ得点を加算する
    $myMovieScores = $prefs[$user];
    $otherMovieScores = $prefs[$other];

    foreach (array_keys($otherMovieScores) as $otherMovie) {
      // 自分が見ていないアイテムだった場合
      if (!in_array($otherMovie, array_keys($myMovieScores))) {

        $total = 0.0;
        $simSum = 0.0;
        
        if (isset($totals[$otherMovie])) {
          $total += $totals[$otherMovie];
          $simSum += $simSums[$otherMovie];
        }
        $total += $otherMovieScores[$otherMovie] * $sim;
        $simSum += $sim;
        $totals[$otherMovie] = $total;
        $simSums[$otherMovie] = $simSum;
      }
    }
  }
  // 正規化したリストを作る
  $scoreList = [];
  foreach($totals as $movie => $value) {
    $ranking = $totals[$movie] / $simSums[$movie];
    $scoreList[] = new Score($movie, $ranking);
  }
  // スコアリストを返す
  return $scoreList;
}

function getCollaborativeFiltering($data, $rec_data, $my_id){
  $rec = getRecommendations($data, $my_id);
  $rec_articles = [];
  foreach($rec as $r){
    $total_count = 0.0;
    foreach($rec_data as $re){
      $total_count += $re;
    }
    if(!isset($rec_data[$r->itemName])){
      $rec_data[$r->itemName] = 0;
    }
    $h = $r->score;
    $rec_articles[$r->itemName] = $h;
  }
  // 高い順
  arsort($rec_articles);
  return $rec_articles;
}


function getSuperCollaborativeFiltering($data, $rec_data, $my_id){
  $rec = getRecommendations($data, $my_id);
  $rec_articles = [];
  foreach($rec as $r){
    $total_count = 0.0;
    foreach($rec_data as $re){
      $total_count += $re;
    }
    if(!isset($rec_data[$r->itemName])){
      $rec_data[$r->itemName] = 0;
    }
    $h = $r->score * log($total_count / ($rec_data[$r->itemName] + 1));
    $rec_articles[$r->itemName] = $h;
  }
  // 高い順
  arsort($rec_articles);
  return $rec_articles;
}
