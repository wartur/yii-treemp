CREATE TABLE `treetest` (
	`id`		INT(11)			NOT NULL	AUTO_INCREMENT,
	`name`		VARCHAR(255)	NOT NULL,
	`parent_id`	INT(11)				NULL,
	`path`		VARCHAR(255)	NOT NULL,
	PRIMARY KEY (`id`),
	UNIQUE KEY `path` (`path`),
);