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
use pocketmine\network\mcpe\protocol\serializer\CommonTypes;
use pocketmine\network\mcpe\protocol\types\inventory\InventoryTransactionChangedSlotsHack;
use pocketmine\network\mcpe\protocol\types\inventory\UseItemTransactionData;
use function count;

final class ItemInteractionData{
	/**
	 * @param InventoryTransactionChangedSlotsHack[]|null $requestChangedSlots
	 */
	public function __construct(
		private int $requestId,
		private ?array $requestChangedSlots,
		private UseItemTransactionData $transactionData
	){}

	public function getRequestId() : int{
		return $this->requestId;
	}

	/**
	 * @return InventoryTransactionChangedSlotsHack[]|null
	 */
	public function getRequestChangedSlots() : ?array{
		return $this->requestChangedSlots;
	}

	public function getTransactionData() : UseItemTransactionData{
		return $this->transactionData;
	}

	public static function read(ByteBufferReader $in, int $protocolId) : self{
		$requestId = VarInt::readSignedInt($in);
		$requestChangedSlots = null;
		if($protocolId >= ProtocolInfo::PROTOCOL_1_26_40){
			$requestChangedSlots = CommonTypes::readOptional($in, static function(ByteBufferReader $in) : array{
				$slots = [];
				for($i = 0, $len = VarInt::readUnsignedInt($in); $i < $len; ++$i){
					$slots[] = InventoryTransactionChangedSlotsHack::read($in);
				}
				return $slots;
			});
		}elseif($requestId !== 0){
			$requestChangedSlots = [];
			for($i = 0, $len = VarInt::readUnsignedInt($in); $i < $len; ++$i){
				$requestChangedSlots[] = InventoryTransactionChangedSlotsHack::read($in);
			}
		}
		$transactionData = new UseItemTransactionData();
		if($protocolId >= ProtocolInfo::PROTOCOL_1_26_50){
			//since 1.26.50 the transaction is framed exactly like in InventoryTransactionPacket
			$transactionData->decodeTransaction($in, $protocolId);
		}else{
			if($protocolId >= ProtocolInfo::PROTOCOL_1_26_40){
				//two dummy optionals which are always present
				self::readDummyOptional($in);
				self::readDummyOptional($in);
			}
			$transactionData->decodeAuthInput($in, $protocolId);
		}
		return new ItemInteractionData($requestId, $requestChangedSlots, $transactionData);
	}

	/** @throws PacketDecodeException */
	private static function readDummyOptional(ByteBufferReader $in) : void{
		$dummy = Byte::readUnsigned($in);
		if($dummy !== 1){
			throw new PacketDecodeException("Dummy optional first byte should always be 1, got $dummy");
		}
	}

	public function write(ByteBufferWriter $out, int $protocolId) : void{
		VarInt::writeSignedInt($out, $this->requestId);
		if($protocolId >= ProtocolInfo::PROTOCOL_1_26_40){
			CommonTypes::writeOptional($out, $this->requestChangedSlots, static function(ByteBufferWriter $out, array $slots) : void{
				VarInt::writeUnsignedInt($out, count($slots));
				foreach($slots as $changedSlot){
					$changedSlot->write($out);
				}
			});
			if($protocolId < ProtocolInfo::PROTOCOL_1_26_50){
				Byte::writeUnsigned($out, 1);
				Byte::writeUnsigned($out, 1);
			}
		}elseif($this->requestId !== 0){
			VarInt::writeUnsignedInt($out, count($this->requestChangedSlots ?? []));
			foreach($this->requestChangedSlots ?? [] as $changedSlot){
				$changedSlot->write($out);
			}
		}
		if($protocolId >= ProtocolInfo::PROTOCOL_1_26_50){
			$this->transactionData->encodeTransaction($out, $protocolId);
		}else{
			$this->transactionData->encodeAuthInput($out, $protocolId);
		}
	}
}
