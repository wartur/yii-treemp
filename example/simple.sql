-- phpMyAdmin SQL Dump
-- version 3.4.11.1
-- http://www.phpmyadmin.net
--
-- Host: 127.0.0.1
-- Generation Time: Jan 08, 2015 at 02:04 AM
-- Server version: 1.0.15
-- PHP Version: 5.5.20-1+deb.sury.org~trusty+1

SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO";
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
-- Table structure for table `attachtest`
--

DROP TABLE IF EXISTS `attachtest`;
CREATE TABLE IF NOT EXISTS `attachtest` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `many_attach_model_id` int(11) NOT NULL,
  `treetest_id` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `attachmodel_treetest` (`many_attach_model_id`,`treetest_id`),
  UNIQUE KEY `treetest_attachmodel` (`treetest_id`,`many_attach_model_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- Table structure for table `many_attach_model`
--

DROP TABLE IF EXISTS `many_attach_model`;
CREATE TABLE IF NOT EXISTS `many_attach_model` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- Table structure for table `one_attach_model`
--

DROP TABLE IF EXISTS `one_attach_model`;
CREATE TABLE IF NOT EXISTS `one_attach_model` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `treetest_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `treetest_id` (`treetest_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- Table structure for table `treetest`
--

DROP TABLE IF EXISTS `treetest`;
CREATE TABLE IF NOT EXISTS `treetest` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `path` varchar(255) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `path` (`path`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `attachtest`
--
ALTER TABLE `attachtest`
  ADD CONSTRAINT `attachtest_ibfk_1` FOREIGN KEY (`many_attach_model_id`) REFERENCES `many_attach_model` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `attachtest_ibfk_2` FOREIGN KEY (`treetest_id`) REFERENCES `treetest` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `one_attach_model`
--
ALTER TABLE `one_attach_model`
  ADD CONSTRAINT `one_attach_model_ibfk_2` FOREIGN KEY (`treetest_id`) REFERENCES `treetest` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
