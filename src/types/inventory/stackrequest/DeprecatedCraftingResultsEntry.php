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

namespace pocketmine\network\mcpe\protocol\types\inventory\stackrequest;

use pmmp\encoding\ByteBufferReader;
use pmmp\encoding\ByteBufferWriter;
use pmmp\encoding\LE;
use pmmp\encoding\VarInt;
use pocketmine\network\mcpe\protocol\ProtocolInfo;
use pocketmine\network\mcpe\protocol\serializer\CommonTypes;
use pocketmine\network\mcpe\protocol\types\inventory\ItemStack;
use pocketmine\network\mcpe\protocol\types\recipe\IntIdMetaItemDescriptor;
use pocketmine\network\mcpe\protocol\types\recipe\ItemDescriptor;

/**
 * Seems pointless, but we have to account for it \_(ツ)_/
 */
final class DeprecatedCraftingResultsEntry{

	public function __construct(
		private ?ItemDescriptor $descriptor,
		private int $count,
		private int $blockRuntimeId,
		private string $rawExtraData
	){}

	public function getDescriptor() : ?ItemDescriptor{ return $this->descriptor; }

	public function getCount() : int{ return $this->count; }

	public function getBlockRuntimeId() : int{ return $this->blockRuntimeId; }

	public function getRawExtraData() : string{ return $this->rawExtraData; }

	public static function read(ByteBufferReader $in, int $protocolId) : self{
		if($protocolId < ProtocolInfo::PROTOCOL_1_26_40){
			$itemStack = CommonTypes::getItemStackWithoutStackId($in, $protocolId);

			return new self(
				new IntIdMetaItemDescriptor($itemStack->getId(), $itemStack->getMeta()),
				$itemStack->getCount(),
				$itemStack->getBlockRuntimeId(),
				$itemStack->getRawExtraData()
			);
		}

		$descriptor = CommonTypes::getItemDescriptorNormal($in, $protocolId);
		$count = LE::readUnsignedShort($in);
		$blockRuntimeId = VarInt::readUnsignedInt($in);
		$rawExtraData = CommonTypes::getString($in);

		return new self($descriptor, $count, $blockRuntimeId, $rawExtraData);
	}

	public function write(ByteBufferWriter $out, int $protocolId) : void{
		if($protocolId < ProtocolInfo::PROTOCOL_1_26_40){
			$itemStack = $this->descriptor instanceof IntIdMetaItemDescriptor ?
				new ItemStack(
					$this->descriptor->getId(),
					$this->descriptor->getMeta(),
					$this->count,
					$this->blockRuntimeId,
					$this->rawExtraData
				) :
				ItemStack::null();
			CommonTypes::putItemStackWithoutStackId($out, $protocolId, $itemStack);
			return;
		}

		CommonTypes::putItemDescriptorNormal($out, $protocolId, $this->descriptor);
		LE::writeUnsignedShort($out, $this->count);
		VarInt::writeUnsignedInt($out, $this->blockRuntimeId);
		CommonTypes::putString($out, $this->rawExtraData);
	}
}
