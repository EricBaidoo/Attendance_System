DROP PROCEDURE IF EXISTS fix_auto_increment_all;
DELIMITER $$
CREATE PROCEDURE fix_auto_increment_all()
BEGIN
    DECLARE done INT DEFAULT 0;
    DECLARE t VARCHAR(128);

    DECLARE cur CURSOR FOR
        SELECT table_name
        FROM information_schema.tables
        WHERE table_schema = 'attendance_system'
          AND AUTO_INCREMENT IS NOT NULL;

    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = 1;

    OPEN cur;

    read_loop: LOOP
        FETCH cur INTO t;
        IF done = 1 THEN
            LEAVE read_loop;
        END IF;

        SET @mx_sql = CONCAT('SELECT IFNULL(MAX(id),0)+1 INTO @next_id FROM `attendance_system`.`', t, '`');
        PREPARE s1 FROM @mx_sql;
        EXECUTE s1;
        DEALLOCATE PREPARE s1;

        IF @next_id < 1 THEN
            SET @next_id = 1;
        END IF;

        SET @al_sql = CONCAT('ALTER TABLE `attendance_system`.`', t, '` AUTO_INCREMENT = ', @next_id);
        PREPARE s2 FROM @al_sql;
        EXECUTE s2;
        DEALLOCATE PREPARE s2;
    END LOOP;

    CLOSE cur;
END$$
DELIMITER ;

CALL fix_auto_increment_all();
DROP PROCEDURE IF EXISTS fix_auto_increment_all;
