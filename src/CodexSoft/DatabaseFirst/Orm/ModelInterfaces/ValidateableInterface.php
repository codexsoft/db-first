<?php

namespace CodexSoft\DatabaseFirst\Orm\ModelInterfaces;

/**
 * Перед сохранением в БД сущности проходят валидацию ($entity->validate()), и она может выбросить
 * исключение.
 *
 * Interface ValidateableInterface
 */
interface ValidateableInterface
{

    public function validate();

}