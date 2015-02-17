<?php

class SV_TitleEditHistory_EditHistoryHandler_Thread extends XenForo_EditHistoryHandler_Abstract
{
    protected $_prefix = 'threadmark';

    protected function _getContent($contentId, array $viewingUser)
    {
        /* @var $postModel XenForo_Model_Post */
        $threadmarkModel = XenForo_Model::create('Sidane_Threadmarks_Model_Threadmarks');

        $threadmark = $threadmarkModel->getThreadById($contentId, array(
            'join' => XenForo_Model_Thread::FETCH_FORUM | XenForo_Model_Thread::FETCH_USER,
            'permissionCombinationId' => $viewingUser['permission_combination_id']
        ));
        if ($threadmark)
        {
            $threadmark['permissions'] = XenForo_Permission::unserializePermissions($threadmark['node_permission_cache']);
        }

        return $threadmark;
    }

    protected function _canViewHistoryAndContent(array $content, array $viewingUser)
    {
        $threadmarkModel = XenForo_Model::create('Sidane_Threadmarks_Model_Threadmarks');

        return $threadmarkModel->canEditThreadTitle($content, $content, $null);
    }

    protected function _canRevertContent(array $content, array $viewingUser)
    {
        $threadmarkModel = XenForo_Model::create('Sidane_Threadmarks_Model_Threadmarks');

        return $threadmarkModel->canEditThreadTitle($content, $content, $null);
    }

    public function getText(array $content)
    {
        return htmlspecialchars($content['label']);
    }

    public function getTitle(array $content)
    {
        //return new XenForo_Phrase('post_in_thread_x', array('label' => $content['label']));
        return htmlspecialchars($content['label']); // TODO
    }

    public function getBreadcrumbs(array $content)
    {
        /* @var $nodeModel XenForo_Model_Node */
        $nodeModel = XenForo_Model::create('XenForo_Model_Node');

        $node = $nodeModel->getNodeById($content['node_id']);
        if ($node)
        {
            $crumb = $nodeModel->getNodeBreadCrumbs($node);
            $crumb[] = array(
                'href' => XenForo_Link::buildPublicLink('full:threads', $content),
                'value' => $content['label']
            );
            return $crumb;
        }
        else
        {
            return array();
        }
    }

    public function getNavigationTab()
    {
        return 'forums';
    }

    public function formatHistory($string, XenForo_View $view)
    {
        return htmlspecialchars($string);
    }

    public function revertToVersion(array $content, $revertCount, array $history, array $previous = null)
    {
        $dw = XenForo_DataWriter::create('Sidane_Threadmarks_DataWriter_Threadmark', XenForo_DataWriter::ERROR_SILENT);
        $dw->setExistingData($content);
        $dw->set('label', $history['old_text']);
        $dw->set('edit_count', $dw->get('edit_count') + 1);
        if ($dw->get('edit_count'))
        {
            if (!$previous || $previous['edit_user_id'] != $content['user_id'])
            {
                // if previous is a mod edit, don't show as it may have been hidden
                $dw->set('last_edit_date', 0);
            }
            else if ($previous && $previous['thread_title_edit_user_id'] == $content['user_id'])
            {
                $dw->set('edit_date', $previous['edit_date']);
                $dw->set('edit_user_id', $previous['edit_user_id']);
            }
        }

        return $dw->save();
    }

}