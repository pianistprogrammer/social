<?php
declare(strict_types=1);


/**
 * Nextcloud - Social Support
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Maxence Lange <maxence@artificial-owl.com>
 * @copyright 2018, Maxence Lange <maxence@artificial-owl.com>
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */


namespace OCA\Social\Migration;


use Closure;
use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Schema\SchemaException;
use Doctrine\DBAL\Types\Type;
use OCA\Social\Db\CoreRequestBuilder;
use OCP\DB\ISchemaWrapper;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;


/**
 * Class Version0002Date20190305091901
 *
 * @package OCA\Social\Migration
 */
class Version0002Date20190305091901 extends SimpleMigrationStep {


	/** @var IDBConnection */
	private $connection;


	/** @var array */
	public static $setAsKeys = [
		[CoreRequestBuilder::TABLE_CACHE_ACTORS, 'id'],
		[CoreRequestBuilder::TABLE_CACHE_DOCUMENTS, 'id'],
		[CoreRequestBuilder::TABLE_SERVER_ACTORS, 'id'],
		[CoreRequestBuilder::TABLE_SERVER_FOLLOWS, 'id'],
		[CoreRequestBuilder::TABLE_SERVER_NOTES, 'id']
	];


	/**
	 * @param IDBConnection $connection
	 */
	public function __construct(IDBConnection $connection) {
		$this->connection = $connection;
	}


	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 *
	 * @return ISchemaWrapper
	 * @throws SchemaException
	 * @throws DBALException
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options
	): ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		foreach (self::$setAsKeys as $edit) {
			list($tableName, $field) = $edit;

			$table = $schema->getTable($tableName);

			$prim = self::getPrimField($field);
			if (!$table->hasColumn($prim)) {
				$table->addColumn($prim, Type::STRING, ['notnull' => false, 'length' => 255]);
			}
		}

		return $schema;
	}


	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 */
	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options) {

		foreach (self::$setAsKeys as $edit) {
			list($tableName, $field) = $edit;

			$prim = self::getPrimField($field);
			$qb = $this->connection->getQueryBuilder();

			/** @noinspection PhpMethodParametersCountMismatchInspection */
			$qb->select('t.' . $field)
			   ->from($tableName, 't');

			$cursor = $qb->execute();
			while ($data = $cursor->fetch()) {
				$id = $data[$field];
				$hash = hash('sha512', $id);
				$update = $this->connection->getQueryBuilder();
				$update->update($tableName);
				$update->set($prim, $update->createNamedParameter($hash));
				$update->where(
					$qb->expr()
					   ->eq($field, $update->createNamedParameter($id))
				);
				$update->execute();
			}
			$cursor->closeCursor();

		}
	}


	/**
	 * @param string $field
	 *
	 * @return string
	 */
	public static function getPrimField(string $field): string {
		return $field . '_prim';
	}

}

