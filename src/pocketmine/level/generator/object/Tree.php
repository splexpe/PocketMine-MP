<?php

/*
 *
 *  ____            _        _   __  __ _                  __  __ ____
 * |  _ \ ___   ___| | _____| |_|  \/  (_)_ __   ___      |  \/  |  _ \
 * | |_) / _ \ / __| |/ / _ \ __| |\/| | | '_ \ / _ \_____| |\/| | |_) |
 * |  __/ (_) | (__|   <  __/ |_| |  | | | | | |  __/_____| |  | |  __/
 * |_|   \___/ \___|_|\_\___|\__|_|  |_|_|_| |_|\___|     |_|  |_|_|
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author PocketMine Team
 * @link http://www.pocketmine.net/
 *
 *
*/

declare(strict_types=1);

namespace pocketmine\level\generator\object;

use pocketmine\block\Block;
use pocketmine\block\BlockFactory;
use pocketmine\block\Leaves;
use pocketmine\block\Sapling;
use pocketmine\block\utils\TreeType;
use pocketmine\level\BlockWriteBatch;
use pocketmine\level\ChunkManager;
use pocketmine\utils\Random;
use function abs;

abstract class Tree{
	/** @var Block */
	protected $trunkBlock;
	/** @var Block */
	protected $leafBlock;

	/** @var int */
	protected $treeHeight;

	public function __construct(Block $trunkBlock, Block $leafBlock, int $treeHeight = 7){
		$this->trunkBlock = $trunkBlock;
		$this->leafBlock = $leafBlock;

		$this->treeHeight = $treeHeight;
	}

	/**
	 * @param ChunkManager  $level
	 * @param int           $x
	 * @param int           $y
	 * @param int           $z
	 * @param Random        $random
	 * @param TreeType|null $type default oak
	 *
	 * @throws \InvalidArgumentException
	 */
	public static function growTree(ChunkManager $level, int $x, int $y, int $z, Random $random, ?TreeType $type = null) : void{
		/** @var null|Tree $tree */
		$tree = null;
		$type = $type ?? TreeType::$OAK;
		if($type === TreeType::$SPRUCE){
			$tree = new SpruceTree();
		}elseif($type === TreeType::$BIRCH){
			if($random->nextBoundedInt(39) === 0){
				$tree = new BirchTree(true);
			}else{
				$tree = new BirchTree();
			}
		}elseif($type === TreeType::$JUNGLE){
			$tree = new JungleTree();
		}elseif($type === TreeType::$OAK){ //default
			$tree = new OakTree();
			/*if($random->nextRange(0, 9) === 0){
				$tree = new BigTree();
			}else{*/

			//}
		}

		if($tree !== null and $tree->canPlaceObject($level, $x, $y, $z, $random)){
			$tree->placeObject($level, $x, $y, $z, $random);
		}
	}


	public function canPlaceObject(ChunkManager $level, int $x, int $y, int $z, Random $random) : bool{
		$radiusToCheck = 0;
		for($yy = 0; $yy < $this->treeHeight + 3; ++$yy){
			if($yy === 1 or $yy === $this->treeHeight){
				++$radiusToCheck;
			}
			for($xx = -$radiusToCheck; $xx < ($radiusToCheck + 1); ++$xx){
				for($zz = -$radiusToCheck; $zz < ($radiusToCheck + 1); ++$zz){
					if(!$this->canOverride($level->getBlockAt($x + $xx, $y + $yy, $z + $zz))){
						return false;
					}
				}
			}
		}

		return true;
	}

	public function placeObject(ChunkManager $level, int $x, int $y, int $z, Random $random) : void{
		$write = new BlockWriteBatch();
		$this->placeTrunk($level, $x, $y, $z, $random, $this->generateChunkHeight($random), $write);
		$this->placeCanopy($level, $x, $y, $z, $random, $write);

		$write->apply($level); //TODO: handle return value on failure
	}

	protected function generateChunkHeight(Random $random) : int{
		return $this->treeHeight - 1;
	}

	protected function placeTrunk(ChunkManager $level, int $x, int $y, int $z, Random $random, int $trunkHeight, BlockWriteBatch $write) : void{
		// The base dirt block
		$write->addBlockAt($x, $y - 1, $z, BlockFactory::get(Block::DIRT));

		for($yy = 0; $yy < $trunkHeight; ++$yy){
			if($this->canOverride($write->fetchBlockAt($level, $x, $y + $yy, $z))){
				$write->addBlockAt($x, $y + $yy, $z, $this->trunkBlock);
			}
		}
	}

	protected function placeCanopy(ChunkManager $level, int $x, int $y, int $z, Random $random, BlockWriteBatch $write) : void{
		for($yy = $y - 3 + $this->treeHeight; $yy <= $y + $this->treeHeight; ++$yy){
			$yOff = $yy - ($y + $this->treeHeight);
			$mid = (int) (1 - $yOff / 2);
			for($xx = $x - $mid; $xx <= $x + $mid; ++$xx){
				$xOff = abs($xx - $x);
				for($zz = $z - $mid; $zz <= $z + $mid; ++$zz){
					$zOff = abs($zz - $z);
					if($xOff === $mid and $zOff === $mid and ($yOff === 0 or $random->nextBoundedInt(2) === 0)){
						continue;
					}
					if(!$write->fetchBlockAt($level, $xx, $yy, $zz)->isSolid()){
						$write->addBlockAt($xx, $yy, $zz, $this->leafBlock);
					}
				}
			}
		}
	}

	protected function canOverride(Block $block) : bool{
		return $block->canBeReplaced() or $block instanceof Sapling or $block instanceof Leaves;
	}
}
