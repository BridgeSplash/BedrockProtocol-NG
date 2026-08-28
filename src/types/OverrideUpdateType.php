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

use pocketmine\network\mcpe\protocol\PacketDecodeException;

/**
 * @see PlayerUpdateEntityOverridesPacket
 */
enum OverrideUpdateType : int{
	use PacketIntEnumTrait;

	case CLEAR_OVERRIDES = 0;
	case REMOVE_OVERRIDE = 1;
	case SET_INT_OVERRIDE = 2;
	case SET_FLOAT_OVERRIDE = 3;

	/** Names sent alongside the ordinal since 1.26.40 */
	public function getName() : string{
		return match($this){
			self::CLEAR_OVERRIDES => "clearoverrides",
			self::REMOVE_OVERRIDE => "removeoverride",
			self::SET_INT_OVERRIDE => "setintoverride",
			self::SET_FLOAT_OVERRIDE => "setfloatoverride",
		};
	}

	public static function fromName(string $name) : self{
		return match($name){
			"clearoverrides" => self::CLEAR_OVERRIDES,
			"removeoverride" => self::REMOVE_OVERRIDE,
			"setintoverride" => self::SET_INT_OVERRIDE,
			"setfloatoverride" => self::SET_FLOAT_OVERRIDE,
			default => throw new PacketDecodeException("Unknown override update type '$name'"),
		};
	}
}
