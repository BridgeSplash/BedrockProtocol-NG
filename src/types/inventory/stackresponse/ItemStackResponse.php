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

namespace pocketmine\network\mcpe\protocol\types\inventory\stackresponse;

use pmmp\encoding\Byte;
use pmmp\encoding\ByteBufferReader;
use pmmp\encoding\ByteBufferWriter;
use pmmp\encoding\VarInt;
use pocketmine\network\mcpe\protocol\PacketDecodeException;
use pocketmine\network\mcpe\protocol\ProtocolInfo;
use pocketmine\network\mcpe\protocol\serializer\CommonTypes;
use function count;

final class ItemStackResponse{

	public const RESULT_OK = 0;
	public const RESULT_ERROR = 1;
	//TODO: there are a ton more possible result types but we don't need them yet and they are wayyyyyy too many for me
	//to waste my time on right now...

	/**
	 * @param ItemStackResponseContainerInfo[]|null $containerInfos
	 */
	public function __construct(
		private int $result,
		private int $requestId,
		private ?array $containerInfos = null
	){
		if($this->result !== self::RESULT_OK && $this->containerInfos !== null && count($this->containerInfos) !== 0){
			throw new \InvalidArgumentException("Container infos must be empty if rejecting the request");
		}
	}

	public function getResult() : int{ return $this->result; }

	public function getRequestId() : int{ return $this->requestId; }

	/** @return ItemStackResponseContainerInfo[]|null */
	public function getContainerInfos() : ?array{ return $this->containerInfos; }

	public static function read(ByteBufferReader $in, int $protocolId) : self{
		$result = Byte::readUnsigned($in);
		$requestId = CommonTypes::readItemStackRequestId($in);
		$readContainerInfos = static function(ByteBufferReader $in) use ($protocolId) : array{
			$containerInfos = [];
			for($i = 0, $len = VarInt::readUnsignedInt($in); $i < $len; ++$i){
				$containerInfos[] = ItemStackResponseContainerInfo::read($in, $protocolId);
			}
			return $containerInfos;
		};
		if($protocolId >= ProtocolInfo::PROTOCOL_1_26_40){
			//the outer optional is always present
			$dummy = Byte::readUnsigned($in);
			if($dummy !== 1){
				throw new PacketDecodeException("Dummy optional first byte should always be 1, got $dummy");
			}
			$containerInfos = CommonTypes::readOptional($in, $readContainerInfos);
		}else{
			$containerInfos = $result === self::RESULT_OK ? $readContainerInfos($in) : null;
		}
		return new self($result, $requestId, $containerInfos);
	}

	public function write(ByteBufferWriter $out, int $protocolId) : void{
		Byte::writeUnsigned($out, $this->result);
		CommonTypes::writeItemStackRequestId($out, $this->requestId);
		$writeContainerInfos = static function(ByteBufferWriter $out, array $containerInfos) use ($protocolId) : void{
			VarInt::writeUnsignedInt($out, count($containerInfos));
			foreach($containerInfos as $containerInfo){
				$containerInfo->write($out, $protocolId);
			}
		};
		if($protocolId >= ProtocolInfo::PROTOCOL_1_26_40){
			Byte::writeUnsigned($out, 1);
			CommonTypes::writeOptional($out, $this->containerInfos, $writeContainerInfos);
		}elseif($this->result === self::RESULT_OK){
			$writeContainerInfos($out, $this->containerInfos ?? []);
		}
	}
}
