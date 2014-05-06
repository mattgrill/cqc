<?php

class dice {
  public static $faces = array();

  public static function roll() {
    return static::$faces[rand(0, count(static::$faces) - 1)];
  }
}

class dice_attack extends dice {
  public static $faces = array('hit', 'hit', 'hit', 'hit', 'crit', 'miss');
}

class dice_defense extends dice {
  public static $faces = array('block', 'block', 'counter', 'miss', 'miss', 'miss');
}

class board {
  public $player_a;
  public $player_b;
  public $turn = 1;
  public $active_player;
  public $defending_player;
  public $action_points = 5;
  public $momentum = 1;

  function __construct($class_a, $class_b){
    $this->player_a = $class_a;
    $this->player_b = $class_b;

    $this->active_player = &$this->player_a;
    $this->defending_player = &$this->player_b;
  }

  function change_active_player(){

    // apply end of round status effects
    $this->active_player->bleed();

    $temp = $this->active_player;
    unset($this->active_player);
    $this->active_player = $this->defending_player;
    unset($this->defending_player);
    $this->defending_player = $temp;
  }

  function calc_action_points($attack, $defense){
    $actions = array(
      'punch' => 2,
      'kick' => 4,
      'cut' => 4,
      'stun' => 4,
      'disarm' => 3,
      'heal cuts' => 4,
      'heal broken bones' => 4,
      'block' => 1,
      'counter' => 2,
      'none' => 0,
    );

    return max(1, $actions[$attack] + $this->active_player->broken_bones - $actions[$defense] - $this->defending_player->broken_bones);
  }

  function check_hit($attack, $defense){
    $attack_roll = dice_attack::roll();
    $defense_roll = dice_defense::roll();
    $hit = FALSE;

    if ($attack_roll === 'crit'){
      $this->momentum++;
      $hit = TRUE;
    }

    if ($attack_roll === 'hit'){
      switch ($defense){
        case 'block':
          if ($defense_roll !== 'block'){
            $hit = TRUE;
          }
          break;

        case 'counter':
          if ($defense_roll !== 'block' && $defense_roll !== 'counter'){
            $hit = TRUE;
          }
          break;

        case 'none':
        default:
          $hit = TRUE;
          break;
      }
    }

    return array(
      'attack' => $attack_roll,
      'defense' => $defense_roll,
      'hit' => $hit,
    );
  }

  function check_winner(){
    if ($this->player_a->health <= 0){
      return $this->player_b->name . ' Wins!';
    }
    else if ($this->player_b->health <= 0){
      return $this->player_a->name . ' Wins!';
    }
    else {
      return 'No Winner';
    }
  }

  function play_turn(){
    // determine actions
    $attack = $this->active_player->choose_action($this, $this->defending_player);
    $defense = $this->defending_player->choose_response($this, $attack, $this->active_player);

    // deduct points
    $points_used = $this->calc_action_points($attack, $defense);
    $this->action_points -= $points_used;

    // determine results
    $result = $this->check_hit($attack, $defense);

    // apply results
    if($result['hit']){
      switch ($attack) {
        case 'punch' :
          $this->defending_player->take_hit();
          break;

        case 'kick' :
          $this->defending_player->take_broken_bone();
          break;

        case 'cut' :
          $this->defending_player->take_cut();
          break;

        case 'heal cuts':
          $this->active_player->heal_cuts();
          break;

        case 'heal broken bones':
          $this->active_player->heal_broken_bones();
          break;
      }
    }
    else {
      if ($defense === 'counter' && $result['defense'] === 'counter'){
        $this->active_player->take_hit();
      }
    }

    // check for forced player change or winner
    if ($this->check_winner() === 'No Winner'){
      if ($this->action_points <= 0){
        $this->change_active_player();
        
        $this->action_points = 10 + ($this->action_points * -1);
        $this->momentum = 1;
      }
    }

    $this->turn++;

    // return status
    $success = $result['hit'] ? 'success' : 'failure';

    return "Action: $attack (" . $result['attack'] . "), $defense (" . $result['defense'] . "), AP: " . $points_used . ", Result: $success";
  }

  function status(){
    return 'Active Player: ' . $this->active_player->name . ', Action Points: ' . $this->action_points . ', Momentum: ' . $this->momentum;
  }

}

class player {
  public $name;
  public $health = 20;
  public $broken_bones = 0;
  public $broken_bones_max = 2;
  public $cuts = 0;
  public $cuts_max = 2;
  public $knives = 1;
  public $bandages = 1;
  public $splints = 1;

  function __construct($name){
    $this->name = $name;
  }

  function take_hit(){
    $this->health--;
  }

  /**
   * Lose 1 health
   * Take one cut if not at max
   * If at max, take one broken bone
   */
  function take_cut(){
    $this->take_hit();
    if ($this->cuts < $this->cuts_max){
      $this->cuts++;
    }
    else {
      if ($this->broken_bones < $this->broken_bones_max){
        $this->broken_bones++;
      }
    }
  }

  /**
   * Lose 1 health
   * Take one broken bone if not at max
   * If at max, take one cut
   */
  function take_broken_bone(){
    $this->take_hit();
    if ($this->broken_bones < $this->broken_bones_max){
      $this->broken_bones++;
    }
    else {
      if ($this->cuts < $this->cuts_max){
        $this->cuts++;
      }
    }
  }

  function heal_cuts(){
    if ($this->bandages > 0){
      $this->bandages--;
      $this->cuts = 0;
    }
  }

  function heal_broken_bones(){
    if ($this->splints > 0){
      $this->splints--;
      $this->broken_bones = 0;
    }
  }

  function bleed(){
    $this->health -= $this->cuts;
  }

  function choose_action($board, $opponent){
    $actions = array('punch', 'kick', 'cut');

    // if ($this->broken_bones === $this->broken_bones_max){
    if ($this->broken_bones === $this->broken_bones_max && $this->splints > 0){
      return 'heal broken bones';
    }

    if ($this->cuts === $this->cuts_max && $this->bandages > 0){
      return 'heal cuts';
    }

    if ($board->momentum > 1){
      $actions = array('kick', 'cut');
    }

    // Add in stun
    // if ($board->action_points > 8){
    //   return 'stun';
    // }

    return $actions[rand(0, count($actions) - 1)];
  }

  function choose_response($board, $action, $opponent){
    // $actions = array('none', 'block', 'counter');
    $actions = array('none', 'block', 'block', 'block', 'counter');

    // TODO prevent counter on negative / zero value;
    if ($board->calc_action_points($action, 'counter') <= 0){
      $actions = array('none', 'block', 'block', 'block');
    }

    return $actions[rand(0, count($actions) - 1)];
  }

  function status(){
    return 'Name: ' . $this->name
      . ', Health: ' . $this->health
      . ', Broken Bones: ' . $this->broken_bones
      . ', Cuts: ' . $this->cuts
      . ', Knives: ' . $this->knives
      . ', Bandages: ' . $this->bandages
      . ', Splints: ' . $this->splints;
  }
}

function single_game(){
  $board = new board(new player('ANDREW'), new player('BILLY'));
  print $board->status() . PHP_EOL . PHP_EOL;

  while ($board->check_winner() == 'No Winner'){
    print 'Turn: ' . $board->turn . PHP_EOL;
    print $board->status() . PHP_EOL;
    $turn = $board->play_turn();
    print $turn . PHP_EOL;
    print $board->player_a->status() . PHP_EOL;
    print $board->player_b->status() . PHP_EOL;
    print $board->status() . PHP_EOL;
    print PHP_EOL;
  }
  print $board->check_winner() . PHP_EOL;
}

function multiple_games($count) {
  $outcomes = array();

  for ($i = 1; $i <= $count; $i++){
    $board = new board(new player('ANDREW'), new player('BILLY'));

    while ($board->check_winner() == 'No Winner'){
      $turn = $board->play_turn();
    }

    if (!isset($outcomes[$board->check_winner()])){
      $outcomes[$board->check_winner()] = 0;
    }

    $outcomes[$board->check_winner()]++;
  }

  ksort($outcomes);
  print json_encode($outcomes) . PHP_EOL;
}

single_game();

?>