<?php
class EntryController extends Sdx_Controller_Action_Http
{
    public function commentAction()
    {
        //テンプレを使うときはコメントアウトを解除する
        //$this->_disableViewRenderer();
        
        //entryテーブルクラスの取得
        $t_entry = Bd_Orm_Main_Entry::createTable();
        //JOIN予定のAccountテーブルのテーブルクラスを取得
        $t_account = Bd_Orm_Main_Account::createTable();
        
        //INNER JOIN
        $t_entry->addJoinInner($t_account);
        
        //selectを取得するメソッドがgetSelectWithJoinなので注意
        $select = $t_entry->getSelectWithJoin();
        
        //fetchAll()で全て取得して$entryへ入れておく
        $entry = $t_entry->fetchAll($select);
               
        //$entryの内容をテンプレに渡す。
        $this->view->assign("entry_list", $entry);
        
        //確認用のダンプ出力。公開時は消すが、$entry_listに
        //格納されているメソッドなどを見ながら作業したいので
        //ひとまず書いておく。
        //なお、この時点ではインスタンスの出力がされればおｋ
        Sdx_Debug::dump($entry, "Sdx_Debug::dumpの出力結果");
        
    }
}
?>
