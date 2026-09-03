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
use pmmp\encoding\DataDecodeException;
use pocketmine\network\mcpe\protocol\PacketDecodeException;
use pocketmine\network\mcpe\protocol\ProtocolInfo;
use pocketmine\network\mcpe\protocol\serializer\CommonTypes;

final class SubChunkPacketEntryCommon{

	public function __construct(
		private SubChunkPositionOffset $offset,
		private int $requestResult,
		private string $terrainData,
		private ?SubChunkPacketHeightMapInfo $heightMap,
		private ?SubChunkPacketHeightMapInfo $renderHeightMap
	){}

	public function getOffset() : SubChunkPositionOffset{ return $this->offset; }

	public function getRequestResult() : int{ return $this->requestResult; }

	public function getTerrainData() : string{ return $this->terrainData; }

	public function getHeightMap() : ?SubChunkPacketHeightMapInfo{ return $this->heightMap; }

	public function getRenderHeightMap() : ?SubChunkPacketHeightMapInfo{ return $this->renderHeightMap; }

	public static function read(ByteBufferReader $in, int $protocolId, bool $cacheEnabled) : self{
		$offset = SubChunkPositionOffset::read($in);

		$requestResult = Byte::readUnsigned($in);

		if($protocolId >= ProtocolInfo::PROTOCOL_1_26_40){
			$data = CommonTypes::readOptional($in, CommonTypes::getString(...)) ?? "";

			$heightMapData = self::readHeightMap($in, $protocolId, null);
			$renderHeightMapData = self::readHeightMap($in, $protocolId, $heightMapData);
		}else{
			$data = !$cacheEnabled || $requestResult !== SubChunkRequestResult::SUCCESS_ALL_AIR ? CommonTypes::getString($in) : "";

			$heightMapDataType = Byte::readUnsigned($in);
			$heightMapData = match ($heightMapDataType) {
				SubChunkPacketHeightMapType::NO_DATA => null,
				SubChunkPacketHeightMapType::DATA => SubChunkPacketHeightMapInfo::read($in, $protocolId),
				SubChunkPacketHeightMapType::ALL_TOO_HIGH => SubChunkPacketHeightMapInfo::allTooHigh($protocolId),
				SubChunkPacketHeightMapType::ALL_TOO_LOW => SubChunkPacketHeightMapInfo::allTooLow($protocolId),
				default => throw new PacketDecodeException("Unknown heightmap data type $heightMapDataType")
			};

			if($protocolId >= ProtocolInfo::PROTOCOL_1_21_90){
				$renderHeightMapDataType = Byte::readUnsigned($in);
				$renderHeightMapData = match ($renderHeightMapDataType) {
					SubChunkPacketHeightMapType::NO_DATA => null,
					SubChunkPacketHeightMapType::DATA => SubChunkPacketHeightMapInfo::read($in, $protocolId),
					SubChunkPacketHeightMapType::ALL_TOO_HIGH => SubChunkPacketHeightMapInfo::allTooHigh($protocolId),
					SubChunkPacketHeightMapType::ALL_TOO_LOW => SubChunkPacketHeightMapInfo::allTooLow($protocolId),
					SubChunkPacketHeightMapType::ALL_COPIED => $heightMapData,
					default => throw new PacketDecodeException("Unknown render heightmap data type $renderHeightMapDataType")
				};
			}
		}

		return new self(
			$offset,
			$requestResult,
			$data,
			$heightMapData,
			$renderHeightMapData ?? null,
		);
	}

	public function write(ByteBufferWriter $out, int $protocolId, bool $cacheEnabled) : void{
		$this->offset->write($out);

		Byte::writeUnsigned($out, $this->requestResult);

		if($protocolId >= ProtocolInfo::PROTOCOL_1_26_40){
			CommonTypes::writeOptional($out, $this->terrainData, CommonTypes::putString(...));

			self::writeHeightMap($out, $protocolId, $this->heightMap, false);
			self::writeHeightMap($out, $protocolId, $this->renderHeightMap, true);
			return;
		}

		if(!$cacheEnabled || $this->requestResult !== SubChunkRequestResult::SUCCESS_ALL_AIR){
			CommonTypes::putString($out, $this->terrainData);
		}

		if($this->heightMap === null){
			Byte::writeUnsigned($out, SubChunkPacketHeightMapType::NO_DATA);
		}elseif($this->heightMap->isAllTooLow()){
			Byte::writeUnsigned($out, SubChunkPacketHeightMapType::ALL_TOO_LOW);
		}elseif($this->heightMap->isAllTooHigh()){
			Byte::writeUnsigned($out, SubChunkPacketHeightMapType::ALL_TOO_HIGH);
		}else{
			$heightMapData = $this->heightMap; //avoid PHPStan purity issue
			Byte::writeUnsigned($out, SubChunkPacketHeightMapType::DATA);
			$heightMapData->write($out, $protocolId);
		}

		if($protocolId >= ProtocolInfo::PROTOCOL_1_21_90){
			if($this->renderHeightMap === null){
				Byte::writeUnsigned($out, SubChunkPacketHeightMapType::ALL_COPIED);
			}elseif($this->renderHeightMap->isAllTooLow()){
				Byte::writeUnsigned($out, SubChunkPacketHeightMapType::ALL_TOO_LOW);
			}elseif($this->renderHeightMap->isAllTooHigh()){
				Byte::writeUnsigned($out, SubChunkPacketHeightMapType::ALL_TOO_HIGH);
			}else{
				$renderHeightMapData = $this->renderHeightMap; //avoid PHPStan purity issue
				Byte::writeUnsigned($out, SubChunkPacketHeightMapType::DATA);
				$renderHeightMapData->write($out, $protocolId);
			}
		}
	}

	/**
	 * Since 1.26.40 the heightmap type is followed by an optional, instead of the data being implied by the type.
	 *
	 * @throws PacketDecodeException
	 * @throws DataDecodeException
	 */
	private static function readHeightMap(ByteBufferReader $in, int $protocolId, ?SubChunkPacketHeightMapInfo $copyFrom) : ?SubChunkPacketHeightMapInfo{
		$type = Byte::readUnsigned($in);
		$data = CommonTypes::readOptional($in, static fn(ByteBufferReader $in) => SubChunkPacketHeightMapInfo::read($in, $protocolId));

		return match($type){
			SubChunkPacketHeightMapType::NO_DATA => null,
			SubChunkPacketHeightMapType::DATA => $data ?? throw new PacketDecodeException("Heightmap type is DATA but no heightmap data was provided"),
			SubChunkPacketHeightMapType::ALL_TOO_HIGH => SubChunkPacketHeightMapInfo::allTooHigh($protocolId),
			SubChunkPacketHeightMapType::ALL_TOO_LOW => SubChunkPacketHeightMapInfo::allTooLow($protocolId),
			SubChunkPacketHeightMapType::ALL_COPIED => $copyFrom,
			default => throw new PacketDecodeException("Unknown heightmap data type $type")
		};
	}

	private static function writeHeightMap(ByteBufferWriter $out, int $protocolId, ?SubChunkPacketHeightMapInfo $heightMap, bool $copiedWhenNull) : void{
		if($heightMap === null){
			Byte::writeUnsigned($out, $copiedWhenNull ? SubChunkPacketHeightMapType::ALL_COPIED : SubChunkPacketHeightMapType::NO_DATA);
			CommonTypes::putBool($out, false);
			return;
		}

		if($heightMap->isAllTooLow()){
			Byte::writeUnsigned($out, SubChunkPacketHeightMapType::ALL_TOO_LOW);
			CommonTypes::putBool($out, false);
		}elseif($heightMap->isAllTooHigh()){
			Byte::writeUnsigned($out, SubChunkPacketHeightMapType::ALL_TOO_HIGH);
			CommonTypes::putBool($out, false);
		}else{
			Byte::writeUnsigned($out, SubChunkPacketHeightMapType::DATA);
			CommonTypes::writeOptional($out, $heightMap, static fn(ByteBufferWriter $out, SubChunkPacketHeightMapInfo $v) => $v->write($out, $protocolId));
		}
	}
}
