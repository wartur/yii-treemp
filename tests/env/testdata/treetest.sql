-- phpMyAdmin SQL Dump
-- version 3.4.11.1
-- http://www.phpmyadmin.net
--
-- Host: 127.0.0.1
-- Generation Time: Jan 04, 2015 at 02:30 AM
-- Server version: 1.0.15
-- PHP Version: 5.5.20-1+deb.sury.org~trusty+1

SET FOREIGN_KEY_CHECKS=0;
SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT=0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;

--
-- Database: `simple`
--

-- --------------------------------------------------------

--
-- Table structure for table `treetest`
--
-- Creation: Jan 03, 2015 at 11:27 PM
--

DROP TABLE IF EXISTS `treetest`;
CREATE TABLE IF NOT EXISTS `treetest` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `path` varchar(255) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `path` (`path`)
) ENGINE=MEMORY  DEFAULT CHARSET=utf8 AUTO_INCREMENT=10 ;

--
-- Dumping data for table `treetest`
--

INSERT INTO `treetest` (`id`, `name`, `parent_id`, `path`) VALUES
(1, 'a_root1', NULL, '1:'),
(2, 'b_root2', NULL, '2:'),
(3, 'a_root1_sublev1_1', 1, '1:3:'),
(4, 'b_root1_sublev2', 3, '1:3:4:'),
(5, 'c_root1_sublev3', 4, '1:3:4:5:'),
(6, 'b_root2_sublev1', 2, '2:6:'),
(7, 'a_root1_sublev1_2', 1, '1:7:'),
(8, 'a_root1_sublev1_3', 1, '1:8:'),
(9, 'a_root2_sublev1', 2, '2:9:');
SET FOREIGN_KEY_CHECKS=1;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
