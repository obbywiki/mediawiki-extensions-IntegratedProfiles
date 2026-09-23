<?php

namespace MediaWiki\User;

/**
 * Minimal UserIdentity for standalone PHPUnit (when MediaWiki core is not loaded).
 */
interface UserIdentity {

	public function getId(): int;

	public function getName(): string;

	public function isRegistered(): bool;

}
