<?php
class ThreadController extends Sdx_Controller_Action_Http
{
    /**
     * 連続投稿と見なす投稿間隔
     */
    const POST_INTERVAL_SECONDS = 10;
    
    /**
     * 連続投稿回数の上限値
     */
    const MAX_POST_COUNT = 5;
    
    /**
     * 連続投稿を制限する期間
     */
    const POST_LOCK_PERIOD = 30;
    
    public function indexAction()
    {
      Sdx_Debug::dump($this->_getParam('thread_id'), 'title');
    }
    public function addAction()
    {
        
    }
    
    public function deleteAction()
    {
        
    }
    /*
     * スレッド一覧はthreadListActionに集約する。
     * どういう条件で検索されても、全てこのアクションで
     * 処理を行い、レコードリストを返すようにする。
     */
    public function threadListAction()
    {
      /* *
       * JSON で値の配列だけを送信するようにするため
       * テンプレによるレンダリングは止めておく。
       * (HTMLタグが付与されるのを防ぐ)
       */
      $this->_disableViewRenderer();
      
      /* *
       * ①全スレッド取得
       * エントリがあった順
       */
      
      //サブクエリ  
      $t_entry = Bd_Orm_Main_Entry::createTable();
      $sub_sel = $t_entry->getSelect();
      $sub_sel->group('thread_id');
      $sub_sel
        ->setColumns('thread_id')
        ->addColumns(array('newest_date' => 'MAX(entry.created_at)')
      );
      $sub = sprintf('(%s)', $sub_sel->assemble());

      //こっちがメインセレクト
      $t_thread = Bd_Orm_Main_Thread::createTable();
      $main_sel = $t_thread->getSelectWithJoin();
      $main_sel->joinLeft(
        array('sub' => new Zend_Db_Expr($sub)) ,
        'thread.id = sub.thread_id'
      );

      /* *
       * ②ジャンルで絞りこみをかけたい場合
       * テーブル内に既にgenre_idがあるのでそれを使う
       */
      if($this->_getParam('genre_id'))
      {
        $main_sel->add('genre_id', $this->_getParam('genre_id'));       
      }
    
      /* *
       * ③タグも絞込みに使いたい場合。
       * 絞込みに必要なスレッドID番号群を取得する。
       */
      if($this->_getParam('tag_id'))
      {
        $t_threadtag = Bd_Orm_Main_ThreadTag::createTable();
        $tag_sel = $t_threadtag->addJoinInner($t_thread)->getSelectWithJoin();
        
        $tag_sel
          ->setColumns('thread_id')
          ->add('tag_id', $this->_getParam('tag_id'))
          ->group('thread_id')
          ->having('COUNT(tag_id) ='.count($this->_getParam('tag_id')));
      
        //タグ未指定時にエラーになるのを防ぐためif文の中で$main_selにadd
        $main_sel
        ->add('id', $tag_sel->fetchCol());        
      }
      
      /* *
       * ④最後にレコードの取得件数と並び順を指定して
       * レコードリストを取得する
       */
      //１ページには10行まで。$main_selが総数
      $pager = new Sdx_Pager(5, $t_thread->count($main_sel)); 
      $this->view->assign('pager', $pager);//これはページオブジェクトのアサイン     
      
      $main_sel
        ->limitPager($pager)
        ->order(
          array(
            new Zend_Db_Expr('CASE when newest_date IS NULL then 1 else 2 END ASC'), 
            'newest_date DESC', 
            'created_at DESC'
          )
        );

      $thread_list = $t_thread->fetchAll($main_sel);
      
      /* *
       * json関係。selectしたレコードの内容と次ページIDを返す
       */

      //jsonで返すためのdataを用意。レコード情報と次ページID
      $data = array(
        'records' => $thread_list->toArray(), 
        'next_pid' => $pager->getNextPageId()
      );
      
      //json_encodeしてレスポンスも返す。自分でecho不要。
      $this->jsonResponse($data);
      
      /* ---------------------------------------------
       * レスポンスヘッダを編集などしたいときは、以下の手法も可。
       * $this->jsonResponse()の中で行われている処理とほぼ同じ。
       * 
       * $resp = $this->getResponse();
       * $resp
       *   ->setHeader('Content-type','application/json')
       *   ->setBody(json_encode($data)
       * );
       --------------------------------------------- */
    }
    /**
     * エントリ一覧表示
     * 
     * エントリとエントリ作成者の情報を取る
     * SELECT * FROM entry
     * LEFT JOIN account ON account_id = account.id
     * ORDER BY entry.created_at;
     * 
     * スレッド名、スレッド番号表示に必要な情報はjoinいらないので別にとる
     * SELECT * FROM thread WHERE id=スレッド番号;
     */
    public function entryListAction()
    {
      //debug(いらなくなったら消す)
      $data = Sdx_User::getInstance()->getAttribute('post_limit_data');
      Sdx_Debug::dump($data->last_post_time, 'last_post_time');
      Sdx_Debug::dump($data->total_post_count, 'total_post_count');

      //entryテーブルクラスの取得
      $t_entry = Bd_Orm_Main_Entry::createTable();

      //JOIN予定のAccountテーブルのテーブルクラスを取得
      $t_account = Bd_Orm_Main_Account::createTable();
      
      //JOIN
      $t_entry->addJoinLeft($t_account);
                
      //selectを取得
      $select = $t_entry->getSelectWithJoin();
      $select
        ->order('entry.created_at ASC')
        ->add('thread_id', $this->_getParam('thread_id'));
      
      //エントリ情報をアサイン
      $entry_list = $t_entry->fetchAll($select);
      $this->view->assign('entry_list', $entry_list);
      
      //Threadテーブルの情報は別に送る。ここでは1個しかとってないからfetchAll()じゃなくてfind()でおｋ。
      $t_thread = Bd_Orm_Main_Thread::createTable();
      $thread_select = $t_thread
        ->getSelect()->add('id', $this->_getParam('thread_id'));
      $this->view->assign('thread_info', $t_thread->find($thread_select));
      
      //フォームは別メソッドでつくる。
      $form = $this->createForm();
      
      //Validateエラー時のメッセージを出力させるためのif文
      $error_session = $this->_createSession();
      if(isset($error_session->params))
      {
        $form->bind($error_session->params);
        $form->execValidate();
        unset($error_session->params);
      }

      $this->view->assign('form', $form);
    }
    
    private function createForm()
    {
      $form = new Sdx_Form();
      $form
        ->setAction('/thread/'.$this->_getParam('thread_id').'/save-entry') //アクション先を設定
        ->setMethodToPost();     //メソッドをポストに変更
     
      //エレメントをフォームにセット
      $elem = new Sdx_Form_Element_Textarea();
      $elem
        ->setName('body')
        ->addValidator(new Sdx_Validate_NotEmpty('何も入力ないのは寂しいです'))
        ->addValidator(new Bd_Validate_CountCheck(
            Sdx_User::getInstance()->getAttribute('post_limit_data')->total_post_count,
            self::MAX_POST_COUNT, 
            "連続投稿制限中です(#・д・) [ 投稿する！]ボタンを押さずに30秒待ってから投稿してください"
      ));
      $form->setElement($elem);
       
      return $form;
    }
    
    public function saveEntryAction()
    {
      $this->_disableViewRenderer();
      //ログインチェック
      if(!(Sdx_User::getInstance()->hasId()))
      {
        $this->forward500();
      }

      /**
       * 連続投稿制限中(_setPostCounter()機能停止)の動作
       * memo: これメソッドにすべき？ _unlockPostStop()とかにして。
       */
      if(Sdx_User::getInstance()->getAttribute('post_limit_data')->total_post_count >= self::MAX_POST_COUNT)
      {
        //条件の右辺が制限の有効期間(秒)
        if((time() - Sdx_User::getInstance()->getAttribute('post_limit_data')->last_post_time) > self::POST_LOCK_PERIOD)
        {
          Sdx_User::getInstance()->getAttribute('post_limit_data')->total_post_count = 1;
        }
        else
        {
          //submitが続く限り連投制限が解除されないようにするようにするための処理。
          Sdx_User::getInstance()->getAttribute('post_limit_data')->last_post_time = time();
          Sdx_User::getInstance()->getAttribute('post_limit_data')->total_post_count += 1;
        }
      }

      //submitされていれば
      if($this->_getParam('submit'))
      {
        $form = $this->createForm();

        //bindする前に、入力された内容が空白「のみ」だったら空白をカットする
        $str = $this->_getParam('body');
        $trimed_str = preg_replace("/^[　\s]+$/u", "", $str);
        $this->_setParam('body', $trimed_str);

        //Validateを実行するためにformに値をセット
        $form->bind($this->_getAllParams());
                
        if($form->execValidate())
        {      
          $entry = new Bd_Orm_Main_Entry();//データベース入出力関係のクラスはこっちにある。
          $db = $entry->updateConnection();
          $db->beginTransaction();
 
          try
          {
            $entry
              ->setBody($this->_getParam('body'))
              ->setThreadId($this->_getParam('thread_id'))
              ->setAccountId(Sdx_Context::getInstance()->getVar('signed_account')->getId());//$_SESSIONから直接とらないように。
            $entry->save();
            $db->commit();
          }
          catch (Exception $e)
          {
            $db->rollBack();
            throw $e;
          }
          //投稿回数のカウンターを設置
          $this->_setPostCounter();
        }
        else
        {
          $error_session = $this->_createSession();
          $error_session->params = $this->_getAllParams();  
        }
      }     
      $this->redirectAfterSave("thread/{$this->_getParam('thread_id')}/entry-list#entry-form");         
    }
    //Sdx_Session() は Zend_Session_Namespace に接頭辞 "Sdx_" が付く
    private function _createSession()
    {
      //引数がキー名になる。省略するとdefaultキーになる。
      return new Sdx_Session('THREAD_POST_FORM');    
    }

    public function searchAction() 
    {
      $this->view->assign('genre_list', Bd_Orm_Main_Genre::createTable()->fetchAllOrdered('id','DESC'));
      $this->view->assign('tag_list', Bd_Orm_Main_Tag::createTable()->fetchAllOrdered('id','DESC'));
    }

    /**
     * 連続投稿回数のカウンター
     * 
     * 回数の合計はセッションに格納されているので
     * 戻り値は特になし。
     */
    private function _setPostCounter()
    {
      $user = Sdx_User::getInstance();
      
      // 初回コメント投稿時
      if(!$user->getAttribute('post_limit_data'))
      {
        $user->setAttribute('post_limit_data', new stdClass());
        $data = $user->getAttribute('post_limit_data'); 
        $data->last_post_time = time();
        $data->total_post_count = 1;
      }
      // 2回目以降
      else
      { 
        $data = $user->getAttribute('post_limit_data'); //setAttributeは初回のみ

        // POST_INTERVAL_SECONDS 以内なら
        if((time() - $data->last_post_time) <= self::POST_INTERVAL_SECONDS)
        {
          $data->total_post_count += 1; 
          $data->last_post_time = time();
        }
        // POST_INTERVAL_SECONDS を過ぎていれば
        else
        {
          $data->total_post_count = 1;//カウントはリセットする
          $data->last_post_time = time();//次回投稿時はこの時刻と比較
        }
      }
    }
} 
?>
