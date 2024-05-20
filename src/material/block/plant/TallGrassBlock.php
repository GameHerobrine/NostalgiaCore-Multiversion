<?php

class TallGrassBlock extends FlowableBlock{
	public function __construct($meta = 1){
		parent::__construct(TALL_GRASS, $meta, "Tall Grass");
		$this->isReplaceable = true;
		$names = array(
			0 => "Dead Shrub",
			1 => "Tall Grass",
			2 => "Fern",
		);
		$this->name = $names[$this->meta & 0x03];
		$this->hardness = 0;
	}

	public static function onUpdate(Level $level, $x, $y, $z, $type){
		if(StaticBlock::getIsTransparent($level->level->getBlockID($x, $y - 1, $z))){ //Replace with common break method
			if(Utils::chance(15)) ServerAPI::request()->api->entity->drop(new Position($x+0.5, $y, $z+0.5, $level), BlockAPI::getItem(WHEAT_SEEDS));
			$level->fastSetBlockUpdate($x, $y, $z, 0, 0);
			return BLOCK_UPDATE_NORMAL;
		}
		return false;
	}
	
	public static function getAABB(Level $level, $x, $y, $z){
		return null;
	}
	
	public function getDrops(Item $item, Player $player){
		$drops = array();
		if($item->isShears()) $drops[] = array($this->id, $this->meta & 0x03, 1);
		elseif(Utils::chance(15)) $drops[] = array(WHEAT_SEEDS, 0, 1);
		return $drops;
	}
	
	public function place(Item $item, Player $player, Block $block, Block $target, $face, $fx, $fy, $fz){
		$down = $this->getSide(0);
		if($down->getID() == 2 or $down->getID() == 3){
			$this->level->setBlock($block, $this, true, false, true);
			return true;
		} 
		return false;
	}

}
