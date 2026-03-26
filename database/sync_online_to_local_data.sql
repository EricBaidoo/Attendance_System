SET @stage_schema = 'u145148023_attendance_stage';
SET @local_schema = 'attendance_system';
SET @old_sql_mode = @@SESSION.sql_mode;
SET SESSION sql_mode = '';

SET FOREIGN_KEY_CHECKS = 0;

DROP PROCEDURE IF EXISTS sync_stage_to_local;
DELIMITER $$
CREATE PROCEDURE sync_stage_to_local()
BEGIN
		DECLARE done INT DEFAULT 0;
		DECLARE tbl VARCHAR(128);
		DECLARE cols TEXT;

		DECLARE cur CURSOR FOR
				SELECT t.TABLE_NAME
				FROM information_schema.tables t
				JOIN information_schema.tables l
					ON l.table_schema = @local_schema
				 AND l.table_name = t.table_name
				WHERE t.table_schema = @stage_schema
					AND t.table_type = 'BASE TABLE'
				ORDER BY t.table_name;

		DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = 1;

		OPEN cur;

		read_loop: LOOP
				FETCH cur INTO tbl;
				IF done = 1 THEN
						LEAVE read_loop;
				END IF;

				SELECT GROUP_CONCAT(CONCAT('`', c1.COLUMN_NAME, '`') ORDER BY c1.ORDINAL_POSITION SEPARATOR ',')
					INTO cols
				FROM information_schema.columns c1
				JOIN information_schema.columns c2
					ON c2.table_schema = @stage_schema
				 AND c2.table_name = c1.table_name
				 AND c2.column_name = c1.column_name
				WHERE c1.table_schema = @local_schema
					AND c1.table_name = tbl;

				IF cols IS NOT NULL AND cols <> '' THEN
						SET @sql_delete = CONCAT('DELETE FROM `', @local_schema, '`.`', tbl, '`');
						PREPARE stmt_del FROM @sql_delete;
						EXECUTE stmt_del;
						DEALLOCATE PREPARE stmt_del;

						SET @sql_insert = CONCAT(
								'INSERT INTO `', @local_schema, '`.`', tbl, '` (', cols, ') ',
								'SELECT ', cols, ' FROM `', @stage_schema, '`.`', tbl, '`'
						);
						PREPARE stmt_ins FROM @sql_insert;
						EXECUTE stmt_ins;
						DEALLOCATE PREPARE stmt_ins;
				END IF;
		END LOOP;

		CLOSE cur;
END$$
DELIMITER ;

CALL sync_stage_to_local();
DROP PROCEDURE IF EXISTS sync_stage_to_local;

SET FOREIGN_KEY_CHECKS = 1;
SET SESSION sql_mode = @old_sql_mode;
