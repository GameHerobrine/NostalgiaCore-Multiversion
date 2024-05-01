<?php

class BedBlock extends TransparentBlock{
	
	public static $faces = [
		0 => 3,
		1 => 4,
		2 => 2,
		3 => 5,
	];
	const HEAD_DIRECTION_OFFSETS = [
		[0, 1],
		[-1, 0],
		[0, -1],
		[1, 0]
	];
	public function __construct($type = 0){
		parent::__construct(BED_BLOCK, $type, "Bed Block");
		$this->isActivable = true;
		$this->isFullBlock = false;
		$this->hardness = 1;
	}
	//TODO collision box
	public static function findStandUpPosition(Level $level, $x, $y, $z){
		$blockMeta = $level->level->getBlockDamage($x, $y, $z);
		$direction = $blockMeta & 0x3;
		for($v7 = 0; $v7 <= 1; ++$v7){
			$minX = $x - self::HEAD_DIRECTION_OFFSETS[$direction][0] * $v7 - 1;
			$minZ = $z - self::HEAD_DIRECTION_OFFSETS[$direction][1] * $v7 - 1;
			$maxX = $minX + 2;
			$maxZ = $minZ + 2;
			
			for($xCheck = $minX; $xCheck <= $maxX; ++$xCheck){
				for($zCheck = $minZ; $zCheck <= $maxZ; ++$zCheck){
					$idCheck = $level->level->getBlockID($xCheck, $y - 1, $zCheck);
					if(!StaticBlock::getIsTransparent($idCheck) && $level->level->getBlockID($xCheck, $y, $zCheck) == 0 && $level->level->getBlockID($xCheck, $y + 1, $zCheck) == 0){
						return new Vector3($xCheck, $y, $zCheck);
					}
				}
			}
		}
		
		return null;
	}
	
	public function onActivate(Item $item, Player $player){
		if(ServerAPI::request()->api->time->getPhase($player->level) !== "night"){
			$pk = new ChatPacket;
			$pk->message = "You can only sleep at night";
			$player->dataPacket($pk);
			return true;
		}
		
		$blockNorth = $this->getSide(2); //Gets the blocks around them
		$blockSouth = $this->getSide(3);
		$blockEast = $this->getSide(5);
		$blockWest = $this->getSide(4);
		if(($this->meta & 0x08) === 0x08){ //This is the Top part of bed	
			$b = $this;
		}else{ //Bottom Part of Bed
			if($blockNorth->getID() === $this->id and ($blockNorth->meta & 0x08) === 0x08){
				$b = $blockNorth;
			}elseif($blockSouth->getID() === $this->id and ($blockSouth->meta & 0x08) === 0x08){
				$b = $blockSouth;
			}elseif($blockEast->getID() === $this->id and ($blockEast->meta & 0x08) === 0x08){
				$b = $blockEast;
			}elseif($blockWest->getID() === $this->id and ($blockWest->meta & 0x08) === 0x08){
				$b = $blockWest;
			}else{
				$pk = new ChatPacket;
				$pk->message = "This bed is incomplete";
				$player->dataPacket($pk);
				return true;
			}
		}

		if($player->sleepOn($b) === false){
			$pk = new ChatPacket;
			$pk->message = "This bed is occupied";
			$player->dataPacket($pk);
		}
		return true;
	}
	
	public function place(Item $item, Player $player, Block $block, Block $target, $face, $fx, $fy, $fz){
		$down = $this->getSide(0);
		if($down->isTransparent === false){
			
			$d = $player->entity->getDirection();
			$next = $this->getSide(self::$faces[(($d + 3) % 4)]);
			$downNext = $next->getSide(0);
			if($next->isReplaceable === true and $downNext->isTransparent === false){
				$meta = (($d + 3) % 4) & 0x03;
				$this->level->setBlock($block, BlockAPI::get($this->id, $meta), true, false, true);
				$this->level->setBlock($next, BlockAPI::get($this->id, $meta | 0x08), true, false, true);
				return true;
			}
		}
		return false;
	}	
	
	public function onBreak(Item $item, Player $player){
		$blockNorth = $this->getSide(2); //Gets the blocks around them
		$blockSouth = $this->getSide(3);
		$blockEast = $this->getSide(5);
		$blockWest = $this->getSide(4);
		
		if(($this->meta & 0x08) === 0x08){ //This is the Top part of bed			
			switch($this->meta & 0x7){
				case 0:
					if($blockNorth->id === $this->id) $this->level->setBlock($blockNorth, new AirBlock(), true, false, true);
					break;
				case 1:
					if($blockEast->id === $this->id) $this->level->setBlock($blockEast, new AirBlock(), true, false, true);
					break;
				case 2:
					if($blockSouth->id === $this->id) $this->level->setBlock($blockSouth, new AirBlock(), true, false, true);
					break;
				case 3:
					if($blockWest->id === $this->id) $this->level->setBlock($blockWest, new AirBlock(), true, false, true);
					break;
			}
		}else{ //Bottom Part of Bed
			switch($this->meta & 0x7){
				case 0:
					if($blockSouth->id === $this->id) $this->level->setBlock($blockSouth, new AirBlock(), true, false, true);
					break;
				case 1:
					if($blockWest->id === $this->id) $this->level->setBlock($blockWest, new AirBlock(), true, false, true);
					break;
				case 2:
					if($blockNorth->id === $this->id) $this->level->setBlock($blockNorth, new AirBlock(), true, false, true);
					break;
				case 3:
					if($blockEast->id === $this->id) $this->level->setBlock($blockEast, new AirBlock(), true, false, true);
					break;
			}
		}
		$this->level->setBlock($this, new AirBlock(), true, false, true);
		return true;
	}
	
	public function getDrops(Item $item, Player $player){
		return array(
			array(BED, 0, 1),
		);
	}
	
}