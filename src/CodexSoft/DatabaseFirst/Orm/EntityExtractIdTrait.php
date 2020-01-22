<?php

namespace CodexSoft\DatabaseFirst\Orm;

trait EntityExtractIdTrait
{
    /**
     * Выдаст ID, если передать ID, иначе вытащит id из сущности через getId()
     *
     * В некоторых случаях, чтобы не делать лишние запросы в БД, имея на руках ID
     * или сущность, можно пользоваться этим методом.
     *
     * @param int|string|static $idOrEntity
     *
     * @return int
     * @throws \RuntimeException
     */
    public static function extractId($idOrEntity): int
    {
        $id = static::extractIdOrNull($idOrEntity);
        if ($id === null) {
            throw new \RuntimeException("ID '$idOrEntity' is not entity nor numeric value");
        }

        return $id;
    }

    /**
     * todo: проверить что число >0 ?
     * @param int|string|static $idOrEntity
     *
     * @return int|null
     */
    public static function extractIdOrNull($idOrEntity): ?int
    {
        if (\is_string($idOrEntity) && \is_numeric($idOrEntity)) {
            return (int) $idOrEntity;
        }

        if (\is_int($idOrEntity)) {
            return $idOrEntity;
        }

        if ($idOrEntity instanceof static) {

            if (!\method_exists('getId', $idOrEntity)) {
                throw new \RuntimeException('Entity of class '.static::class.' has NOT getId method!');
            }

            if ($idOrEntity->getId() === null) {
                throw new \RuntimeException('Entity of class '.static::class.' has not ID yet!');
            }

            return $idOrEntity->getId();
        }

        return null;
    }
}
