<?php

class Deck {
  public $id; 
  public $name;
  public $archetype; 
  public $notes;
  
  public $sideboard_cards = array(); // Has many sideboard_cards through deckcontents (issideboard = 1)
  public $maindeck_cards = array(); // Has many maindeck_cards through deckcontente (issideboard = 0)

  public $playername; // Belongs to player through entries
  public $eventname; // Belongs to event thorugh entries

  public $medal; // has a medal

  function __construct($id) { 
    if ($id == 0) { 
      $this->id = 0;
      return;
    } 
    $database = Database::getConnection(); 
    $stmt = $database->prepare("SELECT name, archetype, notes 
      FROM decks d 
      WHERE id = ?");
    $stmt->bind_param("d", $id);
    $stmt->execute();
    $stmt->bind_result($this->name, $this->archetype, $this->notes);
    
    if ($stmt->fetch() == NULL) { 
      throw new Exception('Deck id '. $id .' has not been found.');
    }

    $this->id = $id; 

    $stmt->close();
    // Retrieve cards.
    $stmt = $database->prepare("SELECT c.name, dc.qty, dc.issideboard
      FROM cards c, deckcontents dc, decks d
      WHERE d.id = dc.deck AND c.id = dc.card AND d.id = ?"); 
    $stmt->bind_param("d", $id);
    $stmt->execute(); 
    $stmt->bind_result($cardname, $cardqty, $isside);

    while ($stmt->fetch()) { 
      if ($isside == 0) {
        $this->maindeck_cards[$cardname] = $cardqty;
      } else { 
        $this->sideboard_cards[$cardname] = $cardqty;
      }
    } 

    $stmt->close();
    // Retrieve player
    $stmt = $database->prepare("SELECT p.name 
      FROM players p, entries e, decks d
      WHERE p.name = e.player AND d.id = e.deck AND d.id = ?");
    $stmt->bind_param("d", $id);
    $stmt->execute(); 
    $stmt->bind_result($this->playername);
    $stmt->fetch(); 

    $stmt->close();
    // Retrieve event 
    $stmt = $database->prepare("SELECT e.name
      FROM events e, entries n, decks d
      WHERE d.id = ? and d.id = n.deck AND n.event = e.name"); 
    $stmt->bind_param("d", $id);
    $stmt->execute(); 
    $stmt->bind_result($this->eventname);
    $stmt->fetch();
    $stmt->close();

    // Retrieve medal 
    $stmt = $database->prepare("SELECT n.medal
      FROM entries n WHERE n.deck = ?");
    $stmt->bind_param("d", $id);
    $stmt->execute(); 
    $stmt->bind_result($this->medal); 
    $stmt->fetch(); 
    $stmt->close();

  }

  function getEntry() { 
    return new Entry($this->eventname, $this->playername);
  } 

  function recordString() { 
    return $this->getEntry()->recordString();
  } 

  function getColorImages() { 
    $count = $this->getColorCounts();
    $str = ""; 
    foreach ($count as $color => $n) { 
      if ($n > 0) { 
        $str = $str . "<img src=\"/images/mana$color.gif\" />";
      } 
    }  
    return $str;
  } 

  function getColorCounts() { 
    $db = Database::getConnection(); 
    $stmt = $db->prepare("SELECT sum(isw*d.qty) AS w, sum(isg*d.qty) AS g,
      sum(isu*d.qty) AS u, sum(isr*d.qty) AS r, sum(isb*d.qty) AS b
      FROM cards c, deckcontents d 
      WHERE d.deck = ? AND c.id = d.card AND d.issideboard != 1"); 
    $stmt->bind_param("d", $this->id);
    $stmt->execute(); 
    $count = array();
    $stmt->bind_result($count["w"], $count["g"], $count["u"], $count["r"], $count["b"]);
    $stmt->fetch();
    
    $stmt->close();
    return $count;
  } 

  function getCastingCosts() { 
    $db = Database::getConnection(); 
    $result = $db->query("SELECT convertedcost AS cc, sum(qty) as s
      FROM cards c, deckcontents d 
      WHERE d.deck = {$this->id} AND c.id = d.card AND d.issideboard = 0
      GROUP BY c.convertedcost HAVING cc > 0"); 

    $convertedcosts = array(); 
    while ($res = $result->fetch_assoc()) { 
      $convertedcosts[$res['cc']] = $res['s']; 
    } 

    return $convertedcosts; 
  } 

  function getEvent() { 
    return new Event($this->eventname); 
  } 

  function getCardCount() { 
    $count = 0; 
    foreach ($this->maindeck_cards as $card => $qty) { 
      $count = $count + $qty; 
    }
    return $count; 
  }  

  function getCreatureCards() { 
    $db = Database::getConnection(); 
    $result = $db->query("SELECT dc.qty, c.name
      FROM deckcontents dc, cards c 
      WHERE c.id = dc.card AND dc.deck = {$this->id} 
      AND c.type LIKE '%Creature%' 
      AND dc.issideboard = 0 
      ORDER BY dc.qty DESC, c.name"); 

    $cards = array(); 
    while ($res = $result->fetch_assoc()) { 
      $cards[$res['name']] = $res['qty'];
    } 

    return $cards;
  } 

  function getLandCards() { 
    $db = Database::getConnection(); 
    $result = $db->query("SELECT dc.qty, c.name
      FROM deckcontents dc, cards c 
      WHERE c.id = dc.card AND dc.deck = {$this->id} 
      AND c.type LIKE '%Land%' 
      AND dc.issideboard = 0 
      ORDER BY dc.qty DESC, c.name"); 

    $cards = array(); 
    while ($res = $result->fetch_assoc()) { 
      $cards[$res['name']] = $res['qty'];
    } 

    return $cards;
  } 

  function getOtherCards() { 
    $db = Database::getConnection(); 
    $result = $db->query("SELECT dc.qty, c.name
      FROM deckcontents dc, cards c 
      WHERE c.id = dc.card AND dc.deck = {$this->id} 
      AND c.type NOT LIKE '%Creature%' AND c.type NOT LIKE '%Land%'
      AND dc.issideboard = 0 
      ORDER BY dc.qty DESC, c.name"); 

    $cards = array(); 
    while ($res = $result->fetch_assoc()) { 
      $cards[$res['name']] = $res['qty'];
    } 

    return $cards;
  } 

  function getMatches() { 
    return $this->getEntry()->getMatches();
  } 

  function canEdit($username) { 
    if (strcasecmp($username, $this->playername) == 0) { 
      return true; 
    } 
    $player = new Player($username);
    if ($player->isSuper()) { 
      return true; 
    } 
    $event = $this->getEvent(); 
    return $event->isHost($username) || $event->isSteward($username);
  } 

  private function getCard($cardname) { 
    $db = Database::getConnection(); 
    $stmt = $db->prepare("SELECT id, name FROM cards WHERE name = ?");
    $stmt->bind_param("s", $cardname); 
    $stmt->execute(); 
    $cardar = array();
    $stmt->bind_result($cardar['id'], $cardar['name']); 
    if (is_null($stmt->fetch())) { 
      $cardar = NULL; 
    } 
    $stmt->close(); 

    return $cardar;
  } 

  function save() { 
    $db = Database::getConnection(); 
    $db->autocommit(FALSE);

    if ($this->id == 0) { 
      // New record.  Set up the decks entry and the Entry.
      $stmt = $db->prepare("INSERT INTO decks (archetype, name, notes) 
        values(?, ?, ?)");
      $stmt->bind_param("sss", $this->archetype, $this->name, $this->notes); 
      $stmt->execute();
      $this->id = $stmt->insert_id;

      $stmt = $db->prepare("UPDATE entries SET deck = {$this->id} WHERE player = ? AND event = ?");
      $stmt->bind_param("ss", $this->playername, $this->eventname);
      $stmt->execute(); 
      if ($stmt->affected_rows != 1) { 
        $db->rollback(); 
        $db->autocommit(TRUE);
        throw new Exception("Can't find entry for {$this->playername} in {$this->eventname}");
      } 
    } else { 
      $stmt = $db->prepare("UPDATE decks SET archetype = ?, name = ?,
        notes = ? WHERE id = ?"); 
      if (!$stmt) { 
        echo $db->error;
      } 
      $stmt->bind_param("sssd", $this->archetype, $this->name, $this->notes, $this->id); 
      if (!$stmt->execute()) { 
        $db->rollback(); 
        $db->autocommit(TRUE);
        throw new Exception('Can\'t update deck '. $this->id); 
      }
    }

    $succ = $db->query("DELETE FROM deckcontents WHERE deck = {$this->id}");

    if (!$succ) {
      $db->rollback(); 
      $db->autocommit(TRUE);
      throw new Exception("Can't update deck contents {$this->id}"); 
    }

    $newmaindeck = array();
    foreach ($this->maindeck_cards as $card => $amt) {
      $cardar = $this->getCard($card);
      if (is_null($cardar)) {
        if (!isset($this->unparsed_cards[$card])) {
          $this->unparsed_cards[$card] = 0;
        }
        $this->unparsed_cards[$card] += $amt;
        continue;
      }
      $stmt = $db->prepare("INSERT INTO deckcontents (deck, card, issideboard, qty) values(?, ?, 0, ?)");
      $stmt->bind_param("ddd", $this->id, $cardar['id'], $amt);
      $stmt->execute();
      $newmaindeck[$cardar['name']] = $amt;
    }

    $this->maindeck_cards = $newmaindeck;

    $newsideboard = array();
    foreach ($this->sideboard_cards as $card => $amt) { 
      $cardar = $this->getCard($card); 
      if (is_null($cardar)) { 
        if (!isset($this->unparsed_side[$card])) { 
          $this->unparsed_side[$card] = 0; 
        } 
        $this->unparsed_side[$card] += $amt; 
        continue; 
      }
      $stmt = $db->prepare("INSERT INTO deckcontents (deck, card, issideboard, qty) values(?, ?, 1, ?)"); 
      $stmt->bind_param("ddd", $this->id, $cardar['id'], $amt); 
      $stmt->execute();
      $newsideboard[$cardar['name']] = $amt;
    }

    $this->sideboard_cards = $newsideboard; 

    $db->commit();
    $db->autocommit(TRUE);
    return true;
  }

}
