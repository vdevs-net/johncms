<?php

/**
 * @package     JohnCMS
 * @link        http://johncms.com
 * @copyright   Copyright (C) 2008-2011 JohnCMS Community
 * @license     LICENSE.txt (see attached file)
 * @version     VERSION.txt (see attached file)
 * @author      http://johncms.com/about
 */

define('_IN_JOHNCMS', 1);

$headmod = 'forumsearch';
require('../incfiles/core.php');
$lng_forum = core::load_lng('forum');
$textl = $lng_forum['search_forum'];
require('../incfiles/head.php');
echo '<div class="phdr"><a href="index.php"><b>' . $lng['forum'] . '</b></a> | ' . $lng['search'] . '</div>';

/*
-----------------------------------------------------------------
Функция подсветки результатов запроса
-----------------------------------------------------------------
*/
function ReplaceKeywords($search, $text)
{
    $search = str_replace('*', '', $search);
    return mb_strlen($search) < 3 ? $text : preg_replace('|(' . preg_quote($search, '/') . ')|siu', '<span style="background-color: #FFFF33">$1</span>', $text);
}

switch ($act) {
    case 'reset':
        /*
        -----------------------------------------------------------------
        Очищаем историю личных поисковых запросов
        -----------------------------------------------------------------
        */
        if (core::$user_id) {
            if (isset($_POST['submit'])) {
                $db->exec("DELETE FROM `cms_users_data` WHERE `user_id` = '" . core::$user_id . "' AND `key` = 'forum_search' LIMIT 1");
                header('Location: search.php'); exit;
            } else {
                echo '<form action="search.php?act=reset" method="post">' .
                     '<div class="rmenu">' .
                     '<p>' . core::$lng['search_history_reset'] . '</p>' .
                     '<p><input type="submit" name="submit" value="' . core::$lng['clear'] . '" /></p>' .
                     '<p><a href="search.php">' . core::$lng['cancel'] . '</a></p>' .
                     '</div>' .
                     '</form>';
            }
        }
        break;

    default:
        /*
        -----------------------------------------------------------------
        Принимаем данные, выводим форму поиска
        -----------------------------------------------------------------
        */
        $search = isset($_GET['search']) ? rawurldecode(trim($_GET['search'])) : false;
        $search_t = isset($_REQUEST['t']);
        $to_history = false;
        echo '<div class="gmenu"><form action="search.php" method="get"><p>' .
             '<input type="text" value="' . ($search ? _e($search) : '') . '" name="search" />' .
             '<input type="submit" value="' . $lng['search'] . '" /><br />' .
             '<input name="t" type="checkbox" value="1" ' . ($search_t ? 'checked="checked"' : '') . ' />&nbsp;' . $lng_forum['search_topic_name'] .
             '</p></form></div>';

        /*
        -----------------------------------------------------------------
        Проверям на ошибки
        -----------------------------------------------------------------
        */
        $error = $search && mb_strlen($search) < 4 || mb_strlen($search) > 64 ? true : false;

        if ($search && !$error) {
            /*
            -----------------------------------------------------------------
            Выводим результаты запроса
            -----------------------------------------------------------------
            */
            $array = explode(' ', $search);
            $count = count($array);
            $query = $db->quote($search);
            $stmt = $db->prepare("
                SELECT COUNT(*) FROM `forum`
                WHERE MATCH (`text`) AGAINST (? IN BOOLEAN MODE)
                AND `type` = '" . ($search_t ? 't' : 'm') . "'" . ($rights >= 7 ? "" : " AND `close` != '1'
            "));
            $stmt->execute([
                $query
            ]);
            $total = $stmt->fetchColumn()
            echo '<div class="phdr">' . $lng['search_results'] . '</div>';
            if ($total > $kmess) {
                echo '<div class="topmenu">' . functions::display_pagination('search.php?' . ($search_t ? 't=1&amp;' : '') . 'search=' . urlencode($search) . '&amp;', $start, $total, $kmess) . '</div>';
            }
            if ($total) {
                $to_history = true;
                $stmt = $db->prepare("
                    SELECT *, MATCH (`text`) AGAINST (? IN BOOLEAN MODE) as `rel`
                    FROM `forum`
                    WHERE MATCH (`text`) AGAINST (? IN BOOLEAN MODE)
                    AND `type` = '" . ($search_t ? 't' : 'm') . "'
                    ORDER BY `rel` DESC
                    LIMIT $start, $kmess
                ");
                $stmt->execute([
                    $query,
                    $query
                ]);
                $i = 0;
                while ($res = $stmt->fetch()) {
                    echo $i % 2 ? '<div class="list2">' : '<div class="list1">';
                    if (!$search_t) {
                        // Поиск только в тексте
                        $res_t = $db->query("SELECT `id`,`text` FROM `forum` WHERE `id` = '" . $res['refid'] . "' LIMIT 1")->fetch();
                        echo '<b>' . _e($res_t['text']) . '</b><br />';
                    } else {
                        // Поиск в названиях тем
                        $res_p = $db->query("SELECT `text` FROM `forum` WHERE `refid` = '" . $res['id'] . "' ORDER BY `id` ASC LIMIT 1")->fetch();
                        $res['text'] = _e($res['text']);
                        foreach ($array as $val) {
                            $res['text'] = ReplaceKeywords($val, $res['text']);
                        }
                        echo '<b>' . $res['text'] . '</b><br />';
                    }
                    echo '<a href="../users/profile.php?user=' . $res['user_id'] . '">' . $res['from'] . '</a> ';
                    echo ' <span class="gray">(' . functions::display_date($res['time']) . ')</span><br/>';
                    $text = $search_t ? $res_p['text'] : $res['text'];
                    foreach ($array as $srch) {
                        if (($pos = mb_strpos(strtolower($res['text']), strtolower(str_replace('*', '', $srch)))) !== false) {
                            break;
                        }
                    }
                    if (!isset($pos) || $pos < 100) {
                        $pos = 100;
                    }
                    $text = preg_replace('#\[c\](.*?)\[/c\]#si', '<div class="quote">\1</div>', $text);
                    $text = functions::checkout(mb_substr($text, ($pos - 100), 400), 1);
                    if (!$search_t) {
                        foreach ($array as $val) {
                            $text = ReplaceKeywords($val, $text);
                        }
                    }
                    echo $text;
                    if (mb_strlen($res['text']) > 500) {
                        echo '...<a href="index.php?act=post&amp;id=' . $res['id'] . '">' . $lng_forum['read_all'] . ' &gt;&gt;</a>';
                    }
                    echo '<br /><a href="index.php?id=' . ($search_t ? $res['id'] : $res_t['id']) . '">' . $lng_forum['to_topic'] . '</a>' . ($search_t ? ''
                            : ' | <a href="index.php?act=post&amp;id=' . $res['id'] . '">' . $lng_forum['to_post'] . '</a>');
                    echo '</div>';
                    ++$i;
                }
            } else {
                echo '<div class="rmenu"><p>' . $lng['search_results_empty'] . '</p></div>';
            }
            echo '<div class="phdr">' . $lng['total'] . ': ' . $total . '</div>';
        } else {
            if ($error) echo functions::display_error(core::$lng['error_wrong_lenght']);
            echo '<div class="phdr"><small>' . $lng['search_help'] . '</small></div>';
        }

        /*
        -----------------------------------------------------------------
        Обрабатываем и показываем историю личных поисковых запросов
        -----------------------------------------------------------------
        */
        if (core::$user_id) {
            $stmt = $db->query("SELECT * FROM `cms_users_data` WHERE `user_id` = '" . core::$user_id . "' AND `key` = 'forum_search' LIMIT 1");
            if ($stmt->rowCount()) {
                $res = $stmt->fetch();
                $history = unserialize($res['val']);
                // Добавляем запрос в историю
                if ($to_history && !in_array($search, $history)) {
                    if (count($history) > 20) array_shift($history);
                    $history[] = $search;
                    $stmt = $db->prepare("UPDATE `cms_users_data` SET
                        `val` = ?
                        WHERE `user_id` = '" . core::$user_id . "' AND `key` = 'forum_search'
                        LIMIT 1
                    ");
                    $stmt->execute([
                        serialize($history)
                    ]);
                }
                sort($history);
                foreach ($history as $val) {
                    $history_list[] = '<a href="search.php?search=' . urlencode($val) . '">' . _e($val) . '</a>';
                }
                // Показываем историю запросов
                echo '<div class="topmenu">' .
                     '<b>' . core::$lng['search_history'] . '</b> <span class="red"><a href="search.php?act=reset">[x]</a></span><br />' .
                     functions::display_menu($history_list) .
                     '</div>';
            } elseif ($to_history) {
                $history[] = $search;
                $stmt = $db->prepare("INSERT INTO `cms_users_data` SET
                    `user_id` = '" . core::$user_id . "',
                    `key` = 'forum_search',
                    `val` = ?
                ");
                $stmt->execute([
                    serialize($history)
                ]);
            }
        }

        // Постраничная навигация
        if (isset($total) && $total > $kmess) {
            echo '<div class="topmenu">' . functions::display_pagination('search.php?' . ($search_t ? 't=1&amp;' : '') . 'search=' . urlencode($search) . '&amp;', $start, $total, $kmess) . '</div>' .
                 '<p><form action="search.php?' . ($search_t ? 't=1&amp;' : '') . 'search=' . urlencode($search) . '" method="post">' .
                 '<input type="text" name="page" size="2"/>' .
                 '<input type="submit" value="' . $lng['to_page'] . ' &gt;&gt;"/>' .
                 '</form></p>';
        }

        echo '<p>' . ($search ? '<a href="search.php">' . $lng['search_new'] . '</a><br />' : '') . '<a href="index.php">' . $lng['forum'] . '</a></p>';
}

require('../incfiles/end.php');