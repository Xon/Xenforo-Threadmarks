<?php

class Sidane_Threadmarks_Install
{
  const default_menu_limit = 8;

  public static function install($existingAddOn, $addOnData)
  {
    $version = isset($existingAddOn['version_id']) ? $existingAddOn['version_id'] : 0;

    $db = XenForo_Application::get('db');
    $tables_created = false;

    if ($version == 0)
    {
      $db->query("
        CREATE TABLE IF NOT EXISTS threadmarks (
          threadmark_id INT UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
          thread_id INT UNSIGNED NOT NULL,
          post_id INT UNSIGNED NOT NULL,
          user_id int not null default 0,
          post_date int not null default 0,
          position int not null default 0,
          message_state enum('visible','moderated','deleted') NOT NULL DEFAULT 'visible',
          edit_count int not null default 0,
          last_edit_date int not null default 0,
          last_edit_user_id int not null default 0,
          label VARCHAR(255) NOT NULL,
          UNIQUE KEY `thread_post_id` (`thread_id`,`post_id`),
          KEY `thread_position` (`thread_id`,`position`),
          KEY `user_id` (`user_id`),
          UNIQUE KEY `post_id` (`post_id`)
        ) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
      ");
      $tables_created = true;
    }

    if ($version == 1)
    {
      self::dropColumn('xf_thread', 'has_threadmarks');
      self::dropIndex('threadmarks', 'thread_id');
    }

    if ($version < 2)
    {
      self::addColumn('xf_thread', 'threadmark_count', 'INT UNSIGNED DEFAULT 0 NOT NULL');
      self::addIndex('threadmarks', 'thread_post_id', array('thread_id','post_id'));

      $db->query("insert ignore into xf_permission_entry (user_group_id, user_id, permission_group_id, permission_id, permission_value, permission_value_int)
        select distinct user_group_id, user_id, convert(permission_group_id using utf8), 'sidane_tm_manage', permission_value, permission_value_int
        from xf_permission_entry
        where permission_group_id = 'forum' and permission_id in ('warn','editAnyPost','deleteAnyPost')
        ");

      $db->query("insert ignore into xf_permission_entry (user_group_id, user_id, permission_group_id, permission_id, permission_value, permission_value_int)
        select distinct user_group_id, user_id, convert(permission_group_id using utf8), 'sidane_tm_add', permission_value, permission_value_int
        from xf_permission_entry
        where permission_group_id = 'forum' and permission_id in ('postReply')
        ");

      $db->query("insert ignore into xf_permission_entry (user_group_id, user_id, permission_group_id, permission_id, permission_value, permission_value_int)
        select distinct user_group_id, user_id, convert(permission_group_id using utf8), 'sidane_tm_delete', permission_value, permission_value_int
        from xf_permission_entry
        where permission_group_id = 'forum' and permission_id in ('deleteOwnPost')
        ");

      $db->query("insert ignore into xf_permission_entry (user_group_id, user_id, permission_group_id, permission_id, permission_value, permission_value_int)
        select distinct user_group_id, user_id, convert(permission_group_id using utf8), 'sidane_tm_edit', permission_value, permission_value_int
        from xf_permission_entry
        where permission_group_id = 'forum' and permission_id in ('editOwnPost')
        ");

      $db->query("insert ignore into xf_permission_entry (user_group_id, user_id, permission_group_id, permission_id, permission_value, permission_value_int)
        select distinct user_group_id, user_id, convert(permission_group_id using utf8), 'sidane_tm_menu_limit', 'use_int', ".self::default_menu_limit."
        from xf_permission_entry
        where permission_group_id = 'forum' and  permission_id in ('viewContent')
        ");

      $db->query("insert ignore into xf_permission_entry (user_group_id, user_id, permission_group_id, permission_id, permission_value, permission_value_int)
        select distinct user_group_id, user_id, convert(permission_group_id using utf8), 'sidane_tm_view', permission_value, permission_value_int
        from xf_permission_entry
        where permission_group_id = 'forum' and  permission_id in ('viewContent')
        ");
    }
    if ($version < 3)
    {
      self::modifyColumn('threadmarks', 'label', 'varchar(100)', 'VARCHAR(255) NOT NULL');
    }

    if ($version < 7)
    {
      self::dropIndex('threadmarks', 'post_id');
      self::addIndex('threadmarks', 'post_id', array('post_id'));
    }

    if ($version <= 9)
    {
      $db->query("
        INSERT IGNORE INTO xf_content_type
            (content_type, addon_id, fields)
        VALUES
            ('threadmark', 'sidaneThreadmarks', '')
      ");

      $db->query("
        INSERT IGNORE INTO xf_content_type_field
            (content_type, field_name, field_value)
        VALUES
            ('threadmark', 'edit_history_handler_class', 'Sidane_Threadmarks_EditHistoryHandler_Threadmark')
           ,('threadmark', 'search_handler_class', 'Sidane_Threadmarks_Search_DataHandler_Threadmark')
      ");

      self::addColumn('threadmarks','position', 'int not null default 0');
      self::addIndex('threadmarks', 'thread_position', array('thread_id', 'position'));

      self::addColumn('threadmarks','user_id', 'int not null default 0');
      $db->query("update threadmarks mark
        join xf_post post on mark.post_id = post.post_id
        set mark.user_id = post.user_id
        where mark.user_id = 0
        ");
      self::addIndex('threadmarks', 'user_id', array('user_id'));
      self::addColumn('threadmarks','post_date', 'int not null default 0');
      $db->query("update threadmarks mark
        join xf_post post on mark.post_id = post.post_id
        set mark.post_date = post.post_date
        where mark.post_date = 0
        ");
      self::addColumn('threadmarks','message_state', "enum('visible','moderated','deleted') NOT NULL DEFAULT 'visible'");
      self::addColumn('threadmarks','edit_count', 'int not null default 0');
      self::addColumn('threadmarks','last_edit_date', 'int not null default 0');
      self::addColumn('threadmarks','last_edit_user_id', 'int not null default 0');

      XenForo_Model::create('XenForo_Model_ContentType')->rebuildContentTypeCache();
    }

    XenForo_Application::defer('Sidane_Threadmarks_Deferred_Cache', array(), null, true);
  }

  public static function uninstall()
  {
    self::dropColumn('xf_thread', 'has_threadmarks');
    self::dropColumn('xf_thread', 'threadmark_count');

    $db = XenForo_Application::get('db');
    $db->query("DROP TABLE IF EXISTS threadmarks");

    $db->delete('xf_permission_entry', "permission_id = 'sidane_tm_manage'");
    $db->delete('xf_permission_entry', "permission_id = 'sidane_tm_add'");
    $db->delete('xf_permission_entry', "permission_id = 'sidane_tm_delete'");
    $db->delete('xf_permission_entry', "permission_id = 'sidane_tm_edit'");
    $db->delete('xf_permission_entry', "permission_id = 'sidane_tm_menu_limit'");
    $db->delete('xf_permission_entry', "permission_id = 'sidane_tm_view'");

    $db->delete('xf_permission_entry_content', "permission_id = 'sidane_tm_manage'");
    $db->delete('xf_permission_entry_content', "permission_id = 'sidane_tm_add'");
    $db->delete('xf_permission_entry_content', "permission_id = 'sidane_tm_delete'");
    $db->delete('xf_permission_entry_content', "permission_id = 'sidane_tm_edit'");
    $db->delete('xf_permission_entry_content', "permission_id = 'sidane_tm_menu_limit'");
    $db->delete('xf_permission_entry_content', "permission_id = 'sidane_tm_view'");

    $db->query("
      DELETE FROM xf_content_type
      WHERE xf_content_type.addon_id = 'sidaneThreadmarks'
    ");

    $db->query("
      DELETE FROM xf_content_type_field
      WHERE xf_content_type_field.field_value = 'Sidane_Threadmarks_EditHistoryHandler_Threadmark'
    ");
    XenForo_Model::create('XenForo_Model_ContentType')->rebuildContentTypeCache();
  }

  public static function modifyColumn($table, $column, $oldDefinition, $definition)
  {
    $db = XenForo_Application::get('db');
    $hasColumn = false;
    if (empty($oldDefinition))
    {
      $hasColumn = $db->fetchRow('SHOW COLUMNS FROM `'.$table.'` WHERE Field = ?', $column);
    }
    else
    {
      $hasColumn = $db->fetchRow('SHOW COLUMNS FROM `'.$table.'` WHERE Field = ? and Type = ?', array($column,$oldDefinition));
    }

    if($hasColumn)
    {
      $db->query('ALTER TABLE `'.$table.'` MODIFY COLUMN `'.$column.'` '.$definition);
    }
  }

  public static function dropColumn($table, $column)
  {
    $db = XenForo_Application::get('db');
    if ($db->fetchRow('SHOW COLUMNS FROM `'.$table.'` WHERE Field = ?', $column))
    {
      $db->query('ALTER TABLE `'.$table.'` drop COLUMN `'.$column.'` ');
    }
  }

  public static function addColumn($table, $column, $definition)
  {
    $db = XenForo_Application::get('db');
    if (!$db->fetchRow('SHOW COLUMNS FROM `'.$table.'` WHERE Field = ?', $column))
    {
      $db->query('ALTER TABLE `'.$table.'` ADD COLUMN `'.$column.'` '.$definition);
    }
  }

  public static function addIndex($table, $index, array $columns)
  {
    $db = XenForo_Application::get('db');
    if (!$db->fetchRow('SHOW INDEX FROM `'.$table.'` WHERE Key_name = ?', $index))
    {
      $cols = '(`'. implode('`,`', $columns). '`)';
      $db->query('ALTER TABLE `'.$table.'` add index `'.$index.'` '. $cols);
    }
  }

  public static function dropIndex($table, $index)
  {
    $db = XenForo_Application::get('db');
    if ($db->fetchRow('SHOW INDEX FROM `'.$table.'` WHERE Key_name = ?', $index))
    {
      $db->query('ALTER TABLE `'.$table.'` drop index `'.$index.'` ');
    }
  }
}
