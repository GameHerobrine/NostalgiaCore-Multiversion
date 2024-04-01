<?php

class TaskTempt extends TaskBase
{
	public function __construct($speed){
		$this->speedMultiplier = $speed;
		$this->targetDistance = 10*10;
	}
	
	public function onStart(EntityAI $ai)
	{
		$this->selfCounter = 1;
	}
	
	public function onEnd(EntityAI $ai)
	{
		$ai->entity->target = false;
	}
	
	public function onUpdate(EntityAI $ai)
	{
		if(!$this->isTargetValid($ai) || $ai->entity->inPanic || $ai->isStarted("TaskMate")){
			$this->reset();
			$this->onEnd($ai);
			return false;
		}
		
		$target = $ai->entity->target;
		$xdiff = ($target->x - $ai->entity->x);
		$ydiff = ($target->y - $ai->entity->y);
		$zdiff = ($target->z - $ai->entity->z);
		$dist = $xdiff*$xdiff + $ydiff*$ydiff + $zdiff*$zdiff;
		$ai->mobController->setLookPosition($target->x, $target->y + $target->getEyeHeight(), $target->z, 30, $ai->entity->getVerticalFaceSpeed());
		if($dist >= 6.25){
			$ai->mobController->setMovingTarget($target->x, $target->y, $target->z, $this->speedMultiplier);
		}else{
			$ai->mobController->headYawIsYaw = true;
		}
		
		
	}
	
	public function isTargetValid(EntityAI $ai){
		$e = $ai->entity;
		if($e->target instanceof Entity && !$e->target->closed && $e->isFood($e->target->player->getHeldItem()->id)){
			$t = $e->target;
			$xDiff = ($t->x - $e->x);
			$yDiff = ($t->y - $e->y);
			$zDiff = ($t->z - $e->z);
			return ($xDiff*$xDiff + $yDiff*$yDiff + $zDiff*$zDiff) <= $this->targetDistance;
		}
		return false;
	}
	
	public function canBeExecuted(EntityAI $ai)
	{
		return !$ai->entity->inPanic && !$ai->isStarted("TaskMate") && $this->tryTargeting($ai);
	}
	
	public function tryTargeting(EntityAI $ai){
		$e = $ai->entity;
		if($e->target instanceof Entity){
			$t = $e->target;
			$xDiff = ($t->x - $e->x);
			$yDiff = ($t->y - $e->y);
			$zDiff = ($t->z - $e->z);
			if(($xDiff*$xDiff + $yDiff*$yDiff + $zDiff*$zDiff) <= $this->targetDistance){
				return true;
			}
		}
		$bestTargetDistance = INF;
		$closestTarget = null;
		foreach($e->level->players as $p){
			if($p->spawned && $e->isFood($p->getHeldItem()->id)){
				$pt = $p->entity;
				$xDiff = $pt->x - $e->x;
				$yDiff = $pt->y - $e->y;
				$zDiff = $pt->z - $e->z;
				$d = ($xDiff*$xDiff + $yDiff*$yDiff + $zDiff*$zDiff);
				if($d <= $this->targetDistance){
					if($bestTargetDistance >= $d){
						$closestTarget = $pt;
						$bestTargetDistance = $d;
					}
				}
			}
		}
		
		if($closestTarget != null){
			$e->target = $closestTarget; //TODO dont save entity object ?
			return true;
		}
		return false;
	}
}