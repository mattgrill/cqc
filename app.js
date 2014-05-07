var colors                        = require('colors'),
    Table                         = require('cli-table'),
    dice                          = function(faces){
      this.faces    = faces || [];
      this.roll     = function(){
        return this.faces[Math.floor(Math.random() * this.faces.length)]
      }
    },
    dice_attack_faces   = ['apple', 'orange', 'red', 'green', 'crit', 'miss'],
    dice_attack         = new dice(dice_attack_faces),
    dice_defense_faces  = ['block', 'block', 'counter', 'miss', 'miss', 'miss'],
    dice_defense        = new dice(dice_defense_faces),
    board               = function(player_a, player_b){
      this.player_a               = player_a;
      this.player_b               = player_b;
      this.turn                   = 1;
      this.active_player          = this.player_a;
      this.defending_player       = this.player_b;
      this.action_points          = 4;
      this.momentum               = 1;
      this.change_active_payer    = function(){
        
        var t_active_player       = this.active_player,
            t_defending_player    = this.defending_player;
        
        this.active_player.bleed();

        this.active_player        = t_defending_player;
        this.defending_player     = t_active_player;

      };
      this.calc_action_points     = function(attack, defense){
        var actions = {
          'punch'                 : 2,
          'kick'                  : 4,
          'cut'                   : 4,
          'stun'                  : 4,
          'disarm'                : 3,
          'heal cuts'             : 4,
          'heal_broken_bones'     : 4,
          'block'                 : 1,
          'counter'               : 2,
          'none'                  : 0
        };
        return Math.max(1, this.active_player.broken_bones - actions[defense] - this.defending_player.broken_bones)
      };
      this.check_hits             = function(attack, defense){
        var attack_roll           = dice_attack.roll(),
            defense_roll          = dice_defense.roll(),
            hit                   = false;
        if(attack_roll === 'crit'){
          hit = true;
        }
        else {
          switch(defense){
            case 'block':
              if(defense_roll !== 'block'){
                hit = true;
              }
              break;
            case 'counter':
              if (defense_roll !== 'block' && defense_roll !== 'counter'){
                hit = true;
              }
              break;
            default:
              hit = true;
              break;
          }
        };
        return {'attack':attack_roll , 'defense':defense_roll , 'hit':hit};
      };
      this.check_winner           = function(){
        var statement;
        if (this.player_a.health <= 0){
          statement = this.player_b.name + ' Wins!';
        }
        else if (this.player_b.health <= 0){
          statement = this.player_a.name + ' Wins!';
        }
        else {
          statement ='No Winner';
        }
        return statement;
      };
      this.play_turn              = function(){
        var attack      = this.active_player.choose_action(this, this.defending_player),
            defense     = this.defending_player.choose_response(this, attack, this.active_player),
            points_used = this.calc_action_points(attack, defense),
            result      = this.check_hits(attack, defense),
            success     = result['hit'] ? 'success' : 'failure';
        this.action_points -= points_used;

        if(this.momentum > 1){
          this.momentum--;
        }

        if(result['attack'] === 'crit'){
          this.momentum++;
        }

        // apply results
        if(result['hit']){
          switch(attack){
            case 'punch':
              this.defending_player.take_hit();
              break;
            case 'kick' :
              this.defending_player.take_broken_bone();
              break;
            case 'cut' :
              this.defending_player.take_cut();
              break;
            case 'heal cuts':
              this.active_player.heal_cuts();
              break;
            case 'heal broken bones':
              this.active_player.heal_broken_bones();
              break;
            case 'stun':
              this.momentum += 3;
              break;
            case 'disarm':
              this.defending_player.become_disarmed();
              break;
          }
        }
        else{
          if(defense === 'counter' && result['defense'] === 'counter'){
            this.active_player.take_hit();
          }
        }

        // check for forced player change or winner
        if(this.check_winner() === 'No Winner'){
          if(this.action_points <= 0){
            this.change_active_payer();
            this.action_points    = 10 + (this.action_points * -1);
            this.momentum         = 1;
          }
        }

        this.turn++;

        return 'Action: ' + attack + '(' + result['attack'] + '), ' + defense + '(' + result['defense'] + '), AP: ' + points_used + ', Result: ' + success;
      };
      this.status                 = function(){
        return 'Active Player: ' + this.active_player.name + ', Action Points: ' + this.action_points + ', Momentum: ' + this.momentum;
      }
    },
    player                        = function(name){
      this.name                   = name || '';
      this.health                 = 29;
      this.broken_bones           = 0;
      this.broken_bones_max       = 2;
      this.cuts                   = 0;
      this.cuts_max               = 2;
      this.knives                 = 1;
      this.bandages               = 1;
      this.splints                = 1;
      
      this.take_hit               = function(){
        this.health--;
      };
      /**
        * Lose 1 health
        * Take one cut if not at max
        * If at max, take one broken bone
      */
      this.take_cut               = function(){
        this.take_hit();
        if(this.cuts < this.cuts_max){
          this.cuts++
        }
        else {
          if(this.broken_bones < this.broken_bones_max){
            this.broken_bones++
          }
        }
      };
      /**
        * Lose 1 health
        * Take one broken bone if not at max
        * If at max, take one cut
      */
      this.take_broken_bone       = function(){
        this.take_hit();
        if (this.broken_bones < this.broken_bones_max){
          this.broken_bones++;
        }
        else {
          if (this.cuts < this.cuts_max){
            this.cuts++;
          }
        }
      };
      this.heal_cuts              = function(){
        if(this.bandages > 0){
          this.bandages--;
          this.cuts = 0;
        }
      }
      this.heal_broken_bones      = function(){
        if(this.splints > 0){
          this.splints--;
          this.broken_bones = 0;
        }
      };
      this.become_disarmed        = function(){
        this.knives = 0;
      }
      this.bleed                  = function(){
        this.health -= this.cuts;
      };
      this.choose_action          = function(board, opponent){
        var actions = ['punch', 'kick'];

        if(opponent.knives > 0){
          actions.push('disarm');
        }
        if(this.knives > 0){
          actions.push('cut');
        }

        if(this.broken_bones === this.broken_bones_max && this.splints > 0){
          return 'heal broken bones';
        }

        if(this.cuts === this.cuts_max && this.bandages > 0){
          return 'heal cuts';
        }

        if(board.momentum > 1){
          actions = ['kick'];
          if(opponent.knives > 0){
            actions.push('disarm');
          }
          if(this.knives > 0){
            actions.push('cut');
          }
        }

        // add in stun
        if(board.action_points > 8 && board.momentum === 1){
          if((~~(Math.random() * 100) + 1) <= 30){
            return 'stun';
          }
        }
        return actions[ ~~(Math.random() * actions.length) + 0 ];
      };
      this.choose_response        = function(board, action, opponent){
        var actions     = ['none', 'block', 'block', 'block', 'counter'];
        if(board.momentum > 1){
          return 'none';
        }
        // todo prevent counter on negative / zero value;
        if(board.calc_action_points(action,'counter') <= 0){
          actions = ['none', 'block', 'block', 'block'];
        }
        return actions[ ~~(Math.random() * actions.length) + 0 ];
      };
      this.status                 = function(){
        var table = new Table({
          'head' : ['Name', 'Health', 'Broken Bones', 'Cuts', 'Knives', 'Bandages', 'Splints'],
          'colWidths': [8, 8, 15, 6, 8, 10, 9]
        });
        table.push([this.name,this.health,this.broken_bones,this.cuts,this.knives,this.bandages,this.splints]);
        return table;
      }
    },
    single_game                   = function(p1,p2){
      var game = new board(new player(p1), new player(p2)),
          turn;
      console.log(game.status().red);
      while(game.check_winner() === 'No Winner'){
        console.log('Turn: ' + String(game.turn).green + '\n');
        console.log(game.status().cyan.inverse);
        turn = game.play_turn();
        console.log(turn);
        console.log(game.player_a.status().toString());
        console.log(game.player_b.status().toString());
        console.log('\n');
      }
      console.log(game.check_winner() + '\n'); 
    },
    multiple_games                = function(count, p1, p2){
      var outcome                 = {
            'count' : count,
            'wins'  : [],
            'sum'   : {}
          },
          i;
      for(i = 1; i <= count; i++){
        var game = new board(new player(p1), new player(p2)),
            turn;
        while(game.check_winner() === 'No Winner'){
          turn = game.play_turn();
        }
        outcome.wins.push(game.check_winner());
      }

      for(i = 0, l = outcome.wins.length; i < l; i++){
        outcome.sum[outcome.wins[i]] = 1 + (outcome.sum[outcome.wins[i]] || 0);
      }

      console.log(outcome.sum);
    }
//single_game('ANDREW', 'BILLY');
multiple_games(100, 'ANDREW', 'BILLY');
