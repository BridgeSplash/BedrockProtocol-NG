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

namespace pocketmine\network\mcpe\protocol\types\recipe;

final class ItemDescriptorType{

	public const INT_ID_META = 1;
	public const MOLANG = 2;
	public const TAG = 3;
	public const STRING_ID_META = 4;
	public const COMPLEX_ALIAS = 5;

	public const EMPTY_ORDINAL = 0;

	/**
	 * Ordinals used on the wire since 1.26.40. These differ from the legacy type IDs above.
	 *
	 * @var int[]
	 * @phpstan-var array<int, int>
	 */
	public const ORDINALS = [
		self::STRING_ID_META => 1,
		self::MOLANG => 2,
		self::TAG => 3,
		self::INT_ID_META => 4,
		self::COMPLEX_ALIAS => 5,
	];

	/**
	 * Names used on the wire since 1.26.40.
	 *
	 * @var string[]
	 * @phpstan-var array<int, string>
	 */
	public const NAMES = [
		self::STRING_ID_META => "name",
		self::MOLANG => "molang",
		self::TAG => "item_tag",
		self::INT_ID_META => "int_id_meta",
		self::COMPLEX_ALIAS => "complex_alias",
	];
}
