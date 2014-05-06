<?php

class dice {
  public static $faces = array();

  public static function roll() {
    $num = rand(0,5);
    if (isset(static::$faces[$num])){
      return static::$faces[$num];
    }
    else {
      return 'miss';
    }
  }

}

class dice_attack extends dice {
  public static $faces = array(
    0 => 'hit',
    1 => 'hit',
    2 => 'hit',
    3 => 'hit',
    4 => 'crit',
    5 => 'miss',
  );
}

class dice_defense extends dice {
  public static $faces = array(
    0 => 'block',
    1 => 'block',
    2 => 'counter',
    3 => 'miss',
    4 => 'miss',
    5 => 'miss',
  );
}

class board {
  public $player_a;
  public $player_b;
  public $turn = 0;
  public $active_player;
  public $defending_player;

  function __construct($class_a, $class_b){
    $this->player_a = $class_a;
    $this->player_b = $class_b;

    $this->active_player = &$this->player_a;
    $this->player_a->action_points = 4;
    $this->player_a->momentum = 1;

    $this->defending_player = &$this->player_b;
  }

  function change_active_player(){
    $temp = $this->active_player;
    unset($this->active_player);
    $this->active_player = $this->defending_player;
    unset($this->defending_player);
    $this->defending_player = $temp;
  }

  function check_winner(){
    if ($this->player_a->health <= 0){
      return 'Player B';
    }
    else if ($this->player_b->health <= 0){
      return 'Player A';
    }
    else {
      return 'No Winner';
    }
  }

  function play_turn(){
    $attack = $this->active_player->choose_action($this->defending_player);
    $defense = $this->defending_player->choose_response($attack, $this->active_player);

    $result = $this->check_hit($attack, $defense);

    switch ($attack) {
      case 'punch' :
        $this->active_player->action_points -= 2;
        if($result['hit'] === TRUE){
          $this->defending_player->take_hit();
        }
        break;
    }

    if ($this->check_winner() === 'No Winner'){
      if ($this->active_player->action_points <= 0){
        $this->change_active_player();
        
        $this->active_player->action_points = 10 + ($this->defending_player->action_points * -1);
        $this->active_player->momentum = 1;

        $this->defending_player->action_points = 0;
        $this->defending_player->momentum = 0;
      }
    }

    return "Action: $attack (" . $result['attack'] . "), $defense (" . $result['defense'] . ")";
  }

  function check_hit($attack, $defense){
    $attack_roll = dice_attack::roll();
    $defense_roll = dice_defense::roll();
    $hit = FALSE;

    if ($attack_roll === 'crit'){
      $this->active_player->momentum++;
    }

    switch ($defense){
      case 'block':

        break;

      case 'counter':

        break;

      case 'none':
      default:
        switch ($attack_roll){
          case 'hit':
          case 'crit':
            $hit = TRUE;
            break;
          case 'miss':
          default:
            break;
        }
        break;
    }

    return array(
      'attack' => $attack_roll,
      'defense' => $defense_roll,
      'hit' => $hit,
    );
  }

  function status(){
    return 'A Health: ' . $this->player_a->health . ', B Health: ' . $this->player_b->health . ', Action Points: ' . $this->active_player->action_points . ', Momentum: ' . $this->active_player->momentum;
  }

}

class player {
  public $health = 20;
  public $action_points = 0;
  public $momentum = 0;
  public $broken_bones = 0;
  public $broken_bones_max = 2;
  public $cuts = 0;
  public $cuts_max = 2;
  public $knives = 1;
  public $bandages = 1;
  public $splints = 1;

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

  function choose_action($opponent){
    return 'punch';
  }

  function choose_response($action, $opponent){
    return 'none';
  }
}

$board = new board(new player(), new player());

while ($board->check_winner() == 'No Winner'){
  $action = $board->play_turn();
  print $board->status() . ' | ' . $action . PHP_EOL;
}

print $board->check_winner() . PHP_EOL;
