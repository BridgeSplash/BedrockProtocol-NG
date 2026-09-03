<?php

/*
 * This file is part of BedrockProtocol.
 * Copyright (C) 2014-2022 PocketMine Team <https://github.com/pmmp/BedrockProtocol>
 *
 * BedrockProtocol is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

declare(strict_types=1);

namespace pocketmine\network\mcpe\protocol\types;

use pmmp\encoding\Byte;
use pmmp\encoding\ByteBufferReader;
use pmmp\encoding\ByteBufferWriter;
use pmmp\encoding\VarInt;
use pocketmine\network\mcpe\protocol\PacketDecodeException;
use pocketmine\network\mcpe\protocol\ProtocolInfo;
use function array_fill;
use function count;

class SubChunkPacketHeightMapInfo{

	private const ENTRY_COUNT = 256;
	/** Since 1.26.50 heights are sent in runs of {@link self::RUN_LENGTH} values, each prefixed by its length. */
	private const ENTRY_COUNT_1_26_50 = 272;
	private const RUN_LENGTH = 16;

	private const TOO_LOW = -1;
	private const TOO_HIGH = 16;

	/**
	 * @param int[] $heights ZZZZXXXX key bit order
	 * @phpstan-param list<int> $heights
	 */
	public function __construct(private array $heights){
		if(count($heights) !== self::ENTRY_COUNT && count($heights) !== self::ENTRY_COUNT_1_26_50){
			throw new \InvalidArgumentException("Expected exactly " . self::ENTRY_COUNT . " or " . self::ENTRY_COUNT_1_26_50 . " heightmap values");
		}
	}

	/** @return int[] */
	public function getHeights() : array{ return $this->heights; }

	public function getHeight(int $x, int $z) : int{
		return $this->heights[(($z & 0xf) << 4) | ($x & 0xf)];
	}

	/**
	 * @throws PacketDecodeException
	 */
	public static function read(ByteBufferReader $in, int $protocolId) : self{
		$heights = [];
		if($protocolId >= ProtocolInfo::PROTOCOL_1_26_50){
			for($i = 0; $i < self::ENTRY_COUNT_1_26_50; $i += self::RUN_LENGTH){
				$runLength = VarInt::readUnsignedInt($in);
				if($runLength !== self::RUN_LENGTH){
					throw new PacketDecodeException("Expected heightmap run length of " . self::RUN_LENGTH . ", got $runLength");
				}
				for($j = 0; $j < self::RUN_LENGTH; ++$j){
					$heights[] = Byte::readSigned($in);
				}
			}
		}else{
			for($i = 0; $i < self::ENTRY_COUNT; ++$i){
				$heights[] = Byte::readSigned($in);
			}
		}
		return new self($heights);
	}

	public function write(ByteBufferWriter $out, int $protocolId) : void{
		if($protocolId >= ProtocolInfo::PROTOCOL_1_26_50){
			for($i = 0; $i < self::ENTRY_COUNT_1_26_50; $i += self::RUN_LENGTH){
				VarInt::writeUnsignedInt($out, self::RUN_LENGTH);
				for($j = 0; $j < self::RUN_LENGTH; ++$j){
					//heightmaps built for older protocols are shorter, pad them with too-low values
					Byte::writeSigned($out, $this->heights[$i + $j] ?? self::TOO_LOW);
				}
			}
			return;
		}
		for($i = 0; $i < self::ENTRY_COUNT; ++$i){
			Byte::writeSigned($out, $this->heights[$i]);
		}
	}

	public static function allTooLow(int $protocolId) : self{
		return new self(array_fill(0, self::entryCount($protocolId), self::TOO_LOW));
	}

	public static function allTooHigh(int $protocolId) : self{
		return new self(array_fill(0, self::entryCount($protocolId), self::TOO_HIGH));
	}

	private static function entryCount(int $protocolId) : int{
		return $protocolId >= ProtocolInfo::PROTOCOL_1_26_50 ? self::ENTRY_COUNT_1_26_50 : self::ENTRY_COUNT;
	}

	public function isAllTooLow() : bool{
		foreach($this->heights as $height){
			if($height >= 0){
				return false;
			}
		}
		return true;
	}

	public function isAllTooHigh() : bool{
		foreach($this->heights as $height){
			if($height <= 15){
				return false;
			}
		}
		return true;
	}
}
