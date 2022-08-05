<?php

class Level{

	public $entities, $tiles, $blockUpdates, $nextSave, $players = [], $level;
	private $time, $startCheck, $startTime, $server, $name, $usedChunks, $changedBlocks, $changedCount, $stopTime;

	public function __construct(PMFLevel $level, Config $entities, Config $tiles, Config $blockUpdates, $name){
		$this->server = ServerAPI::request();
		$this->level = $level;
		$this->level->level = $this;
		$this->entities = $entities;
		$this->tiles = $tiles;
		$this->blockUpdates = $blockUpdates;
		$this->startTime = $this->time = (int) $this->level->getData("time");
		$this->nextSave = $this->startCheck = microtime(true);
		$this->nextSave += 90;
		$this->stopTime = false;
		$this->server->schedule(15, [$this, "checkThings"], [], true);
		$this->server->schedule(20 * 13, [$this, "checkTime"], [], true);
		$this->name = $name;
		$this->usedChunks = [];
		$this->changedBlocks = [];
		$this->changedCount = [];
	}

	public function close(){
		$this->__destruct();
	}

	public function __destruct(){
		if(isset($this->level)){
			$this->save(false, false);
			$this->level->close();
			unset($this->level);
		}
	}

	public function save($force = false, $extra = true){
		if(!isset($this->level)){
			return false;
		}
		if($this->server->saveEnabled === false and $force === false){
			return;
		}

		if($extra !== false){
			$entities = [];
			foreach($this->server->api->entity->getAll($this) as $entity){
				if($entity->class === ENTITY_MOB){
					$entities[] = [
						"id" => $entity->type,
						"Color" => @$entity->data["Color"],
						"Sheared" => @$entity->data["Sheared"],
						"IsBaby" => @$entity->data["IsBaby"],
						"Health" => $entity->health,
						"Pos" => [
							0 => $entity->x,
							1 => $entity->y,
							2 => $entity->z,
						],
						"Rotation" => [
							0 => $entity->yaw,
							1 => $entity->pitch,
						],
					];
				}elseif($entity->class === ENTITY_OBJECT){
					if($entity->type === OBJECT_PAINTING){
						$entities[] = [
							"id" => $entity->type,
							"TileX" => $entity->x,
							"TileY" => $entity->y,
							"TileZ" => $entity->z,
							"Health" => $entity->health,
							"Motive" => $entity->data["Motive"],
							"Pos" => [
								0 => $entity->x,
								1 => $entity->y,
								2 => $entity->z,
							],
							"Rotation" => [
								0 => $entity->yaw,
								1 => $entity->pitch,
							],
						];
					}else{
						$entities[] = [
							"id" => $entity->type,
							"Health" => $entity->health,
							"Pos" => [
								0 => $entity->x,
								1 => $entity->y,
								2 => $entity->z,
							],
							"Rotation" => [
								0 => $entity->yaw,
								1 => $entity->pitch,
							],
						];
					}
				}elseif($entity->class === ENTITY_FALLING){
					$entities[] = [
						"id" => $entity->type,
						"Health" => $entity->health,
						"Tile" => $entity->data["Tile"],
						"Pos" => [
							0 => $entity->x,
							1 => $entity->y,
							2 => $entity->z,
						],
						"Rotation" => [
							0 => 0,
							1 => 0,
						],
					];
				}elseif($entity->class === ENTITY_ITEM){
					$entities[] = [
						"id" => 64,
						"Item" => [
							"id" => $entity->type,
							"Damage" => $entity->meta,
							"Count" => $entity->stack,
						],
						"Health" => $entity->health,
						"Pos" => [
							0 => $entity->x,
							1 => $entity->y,
							2 => $entity->z,
						],
						"Rotation" => [
							0 => 0,
							1 => 0,
						],
					];
				}
			}
			$this->entities->setAll($entities);
			$this->entities->save();
			$tiles = [];
			foreach($this->server->api->tile->getAll($this) as $tile){
				$tiles[] = $tile->data;
			}
			$this->tiles->setAll($tiles);
			$this->tiles->save();

			$blockUpdates = [];
			$updates = $this->server->query("SELECT x,y,z,type,delay FROM blockUpdates WHERE level = '" . $this->getName() . "';");
			if($updates !== false and $updates !== true){
				$timeu = microtime(true);
				while(($bupdate = $updates->fetchArray(SQLITE3_ASSOC)) !== false){
					$bupdate["delay"] = max(1, ($bupdate["delay"] - $timeu) * 20);
					$blockUpdates[] = $bupdate;
				}
			}

			$this->blockUpdates->setAll($blockUpdates);
			$this->blockUpdates->save();

		}

		$this->level->setData("time", (int) $this->time);
		$this->level->doSaveRound();
		$this->level->saveData();
		$this->nextSave = microtime(true) + 45;
	}

	public function getName(){
		return $this->name;//return $this->level->getData("name");
	}

	public function useChunk($X, $Z, Player $player){
		if(!isset($this->usedChunks[$X . "." . $Z])){
			$this->usedChunks[$X . "." . $Z] = [];
		}
		$this->usedChunks[$X . "." . $Z][$player->CID] = true;
		if(isset($this->level)){
			$this->level->loadChunk($X, $Z);
		}
	}

	public function freeAllChunks(Player $player){
		foreach($this->usedChunks as $i => $c){
			unset($this->usedChunks[$i][$player->CID]);
		}
	}

	public function freeChunk($X, $Z, Player $player){
		unset($this->usedChunks[$X . "." . $Z][$player->CID]);
	}

	public function checkThings(){
		if(!isset($this->level)){
			return false;
		}
		$now = microtime(true);
		$this->players = $this->server->api->player->getAll($this);

		if(count($this->changedCount) > 0){
			arsort($this->changedCount);
			$resendChunks = [];
			foreach($this->changedCount as $index => $count){
				if($count < 582){//Optimal value, calculated using the relation between minichunks and single packets
					break;
				}
				foreach($this->players as $p){
					unset($p->chunksLoaded[$index]);
				}
				unset($this->changedBlocks[$index]);
			}
			$this->changedCount = [];

			if(count($this->changedBlocks) > 0){
				foreach($this->changedBlocks as $blocks){
					foreach($blocks as $b){
						$pk = new UpdateBlockPacket;
						$pk->x = $b->x;
						$pk->y = $b->y;
						$pk->z = $b->z;
						$pk->block = $b->getID();
						$pk->meta = $b->getMetadata();
						$this->server->api->player->broadcastPacket($this->players, $pk);
					}
				}
				$this->changedBlocks = [];
			}
		}

		if($this->nextSave < $now){
			foreach($this->usedChunks as $i => $c){
				if(count($c) === 0){
					unset($this->usedChunks[$i]);
					$X = explode(".", $i);
					$Z = array_pop($X);
					$X = array_pop($X);
					if(!$this->isSpawnChunk($X, $Z)){
						$this->level->unloadChunk((int) $X, (int) $Z, $this->server->saveEnabled);
					}
				}
			}
			$this->save(false, false);
		}
	}

	public function isSpawnChunk($X, $Z){
		$spawnX = $this->level->getData("spawnX") >> 4;
		$spawnZ = $this->level->getData("spawnZ") >> 4;
		return abs($X - $spawnX) <= 1 and abs($Z - $spawnZ) <= 1;
	}

	public function getBlockRaw(Vector3 $pos){
		$b = $this->level->getBlock($pos->x, $pos->y, $pos->z);
		return BlockAPI::get($b[0], $b[1], new Position($pos->x, $pos->y, $pos->z, $this));
	}

	public function setBlockRaw(Vector3 $pos, Block $block, $direct = true, $send = true){
		if(($ret = $this->level->setBlock($pos->x, $pos->y, $pos->z, $block->getID(), $block->getMetadata())) === true and $send !== false){
			if($direct === true){
				$pk = new UpdateBlockPacket;
				$pk->x = $pos->x;
				$pk->y = $pos->y;
				$pk->z = $pos->z;
				$pk->block = $block->getID();
				$pk->meta = $block->getMetadata();
				$this->server->api->player->broadcastPacket($this->players, $pk);
			}elseif($direct === false){
				if(!($pos instanceof Position)){
					$pos = new Position($pos->x, $pos->y, $pos->z, $this);
				}
				$block->position($pos);
				$i = ($pos->x >> 4) . ":" . ($pos->y >> 4) . ":" . ($pos->z >> 4);
				if(ADVANCED_CACHE == true){
					Cache::remove("world:{$this->name}:" . ($pos->x >> 4) . ":" . ($pos->z >> 4));
				}
				if(!isset($this->changedBlocks[$i])){
					$this->changedBlocks[$i] = [];
					$this->changedCount[$i] = 0;
				}
				$this->changedBlocks[$i][] = clone $block;
				++$this->changedCount[$i];
			}
		}
		return $ret;
	}

	public function setBlock(Vector3 $pos, Block $block, $update = true, $tiles = false, $direct = false){
		if(!isset($this->level) or (($pos instanceof Position) and $pos->level !== $this) or $pos->x < 0 or $pos->y < 0 or $pos->z < 0){
			return false;
		}

		$ret = $this->level->setBlock($pos->x, $pos->y, $pos->z, $block->getID(), $block->getMetadata());
		if($ret === true){
			if(!($pos instanceof Position)){
				$pos = new Position($pos->x, $pos->y, $pos->z, $this);
			}
			$block->position($pos);

			if($direct === true){
				$pk = new UpdateBlockPacket;
				$pk->x = $pos->x;
				$pk->y = $pos->y;
				$pk->z = $pos->z;
				$pk->block = $block->getID();
				$pk->meta = $block->getMetadata();
				$this->server->api->player->broadcastPacket($this->players, $pk);
			}else{
				$i = ($pos->x >> 4) . ":" . ($pos->y >> 4) . ":" . ($pos->z >> 4);
				if(!isset($this->changedBlocks[$i])){
					$this->changedBlocks[$i] = [];
					$this->changedCount[$i] = 0;
				}
				if(ADVANCED_CACHE == true){
					Cache::remove("world:{$this->name}:" . ($pos->x >> 4) . ":" . ($pos->z >> 4));
				}
				$this->changedBlocks[$i][] = clone $block;
				++$this->changedCount[$i];
			}

			if($update === true){
				$this->server->api->block->blockUpdateAround($pos, BLOCK_UPDATE_NORMAL, 1);
				$this->server->api->entity->updateRadius($pos, 3);
			}
			if($tiles === true){
				if(($t = $this->server->api->tile->get($pos)) !== false){
					$t->close();
				}
			}
		}
		return $ret;
	}

	public function getMiniChunk($X, $Z, $Y){
		if(!isset($this->level)){
			return false;
		}
		return $this->level->getMiniChunk($X, $Z, $Y);
	}

	public function setMiniChunk($X, $Z, $Y, $data){
		if(!isset($this->level)){
			return false;
		}
		$this->changedCount[$X . ":" . $Y . ":" . $Z] = 4096;
		if(ADVANCED_CACHE == true){
			Cache::remove("world:{$this->name}:$X:$Z");
		}
		return $this->level->setMiniChunk($X, $Z, $Y, $data);
	}

	public function loadChunk($X, $Z){
		if(!isset($this->level)){
			return false;
		}
		return $this->level->loadChunk($X, $Z);
	}

	public function unloadChunk($X, $Z, $force = false){
		if(!isset($this->level)){
			return false;
		}

		if($force !== true and $this->isSpawnChunk($X, $Z)){
			return false;
		}
		Cache::remove("world:{$this->name}:$X:$Z");
		return $this->level->unloadChunk($X, $Z, $this->server->saveEnabled);
	}

	public function getOrderedChunk($X, $Z, $Yndex){
		if(!isset($this->level)){
			return false;
		}
		if(ADVANCED_CACHE == true and $Yndex == 0xff){
			$identifier = "world:{$this->name}:$X:$Z";
			if(($cache = Cache::get($identifier)) !== false){
				return $cache;
			}
		}


		$raw = [];
		for($Y = 0; $Y < 8; ++$Y){
			if(($Yndex & (1 << $Y)) > 0){
				$raw[$Y] = $this->level->getMiniChunk($X, $Z, $Y);
			}
		}

		$ordered = "";
		$flag = chr($Yndex);
		for($j = 0; $j < 256; ++$j){
			$ordered .= $flag;
			foreach($raw as $mini){
				$ordered .= substr($mini, $j << 5, 24); //16 + 8
			}
		}
		if(ADVANCED_CACHE == true and $Yndex == 0xff){
			Cache::add($identifier, $ordered, 60);
		}
		return $ordered;
	}

	public function getOrderedMiniChunk($X, $Z, $Y){
		if(!isset($this->level)){
			return false;
		}
		$raw = $this->level->getMiniChunk($X, $Z, $Y);
		$ordered = "";
		$flag = chr(1 << $Y);
		for($j = 0; $j < 256; ++$j){
			$ordered .= $flag . substr($raw, $j << 5, 24); //16 + 8
		}
		return $ordered;
	}

	public function getSafeSpawn($spawn = false){
		if($spawn === false){
			$spawn = $this->getSpawn();
		}
		if($spawn instanceof Vector3){
			$x = (int) round($spawn->x);
			$y = (int) round($spawn->y);
			$z = (int) round($spawn->z);
			if($x < 0 || $x > 255 || $z < 0 || $z > 255){
				return new Position($x, 128, $z, $this);
			}
			for(; $y > 0; --$y){
				$v = new Vector3($x, $y, $z);
				$b = $this->getBlock($v->getSide(0));
				if($b === false){
					return $spawn;
				}elseif(!($b instanceof AirBlock)){
					break;
				}
			}
			for(; $y < 128; ++$y){
				$v = new Vector3($x, $y, $z);
				if($this->getBlock($v->getSide(1)) instanceof AirBlock){
					if($this->getBlock($v) instanceof AirBlock){
						return new Position($x, $y, $z, $this);
					}
				}else{
					++$y;
				}
			}
			return new Position($x, $y, $z, $this);
		}
		return false;
	}

	public function getSpawn(){
		if(!isset($this->level)){
			return false;
		}
		return new Position($this->level->getData("spawnX"), $this->level->getData("spawnY"), $this->level->getData("spawnZ"), $this);
	}

	public function getBlock(Vector3 $pos){
		if(!isset($this->level) or ($pos instanceof Position) and $pos->level !== $this){
			return false;
		}
		$b = $this->level->getBlock($pos->x, $pos->y, $pos->z);
		return BlockAPI::get($b[0], $b[1], new Position($pos->x, $pos->y, $pos->z, $this));
	}

	public function setSpawn(Vector3 $pos){
		if(!isset($this->level)){
			return false;
		}
		$this->level->setData("spawnX", $pos->x);
		$this->level->setData("spawnY", $pos->y);
		$this->level->setData("spawnZ", $pos->z);
	}

	public function getTime(){
		return (int) ($this->time);
	}

	public function setTime($time){
		$this->startTime = $this->time = (int) $time;
		$this->startCheck = microtime(true);
		$this->checkTime();
	}

	public function checkTime(){
		if(!isset($this->level)){
			return false;
		}
		$now = microtime(true);
		if($this->stopTime == true){

		}else{
			$time = $this->startTime + ($now - $this->startCheck) * 20;
		}
		if($this->server->api->dhandle("time.change", ["level" => $this, "time" => $time]) !== false){
			$this->time = $time;

			$pk = new SetTimePacket;
			$pk->time = (int) $this->time;
			$pk->started = $this->stopTime == false;
			$this->server->api->player->broadcastPacket($this->players, $pk);
		}
	}

	public function stopTime(){
		$this->stopTime = true;
		$this->startCheck = 0;
		$this->checkTime();
	}

	public function startTime(){
		$this->stopTime = false;
		$this->startCheck = microtime(true);
		$this->checkTime();
	}

	public function getSeed(){
		if(!isset($this->level)){
			return false;
		}
		return (int) $this->level->getData("seed");
	}

	public function setSeed($seed){
		if(!isset($this->level)){
			return false;
		}
		$this->level->setData("seed", (int) $seed);
	}

	public function scheduleBlockUpdate(Position $pos, $delay, $type = BLOCK_UPDATE_SCHEDULED){
		if(!isset($this->level)){
			return false;
		}
		return $this->server->api->block->scheduleBlockUpdate($pos, $delay, $type);
	}
}
