<?php
/**
 * Created by PhpStorm.
 * User: wodrow
 * Date: 17-5-8
 * Time: 下午6:46
 */

namespace frontend\modules\user\controllers;


use common\config\ConfigData;
use common\models\StoryTag;
use common\models\Tag;
use frontend\modules\user\models\Story;
use frontend\modules\user\models\StorySearch;
use yii\base\ErrorException;
use yii\db\Exception;
use yii\filters\VerbFilter;
use yii\web\Controller;

class StoryController extends Controller
{
    public function behaviors()
    {
        return array_merge(parent::behaviors(), [
            'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [
                    'delete' => ['POST'],
                ],
            ],
        ]);
    }

    private function getYouStory($id){
        if (\Yii::$app->user->id == ConfigData::getSuper()->id){
            $x = Story::findOne(['id'=>$id]);
        }else{
            $x = Story::findOne(['id'=>$id, 'created_by'=>\Yii::$app->user->id]);
        }
        if ($x){
            return $x;
        }else{
            throw new ErrorException('没有找到文章或不是你的文章');
        }
    }

    private function transSave(Story $story)
    {
        $trans = \Yii::$app->db->beginTransaction();
        try{
            if ($story->save()){
                StoryTag::deleteAll(['story_id'=>$story->id]);
                $tag = new Tag();
                $story_tag = new StoryTag();
                foreach ($story->tagArr as $k => $v){
                    $t = Tag::findOne(['name'=>$v]);
                    if (!$t){
                        $t = clone $tag;
                        $t->name = $v;
                        $t->created_at = $t->updated_at = time();
                        $t->created_by = $t->updated_by = \Yii::$app->user->id;
                        $t->save();
                    }
                    $m = clone $story_tag;
                    $m->story_id = $story->id;
                    $m->tag_id = $t->id;
                    $m->save();
                }
            }
            $trans->commit();
            return $this->redirect(['/user/story/view', 'id' => $story->id]);
        }catch (Exception $e){
            $trans->rollBack();
            throw $e;
        }
    }

    public function actionIndex()
    {
        $searchModel = new StorySearch();
        $dataProvider = $searchModel->search(\Yii::$app->request->getQueryParams());
        return $this->render('index', [
            'dataProvider' => $dataProvider,
            'searchModel' => $searchModel,
        ]);
    }

    /**
     * 发布文章
     * @return string
     */
    public function actionPublish()
    {
        $story = new Story();
        if ($story->load(\Yii::$app->request->post())){
            $story->updated_by = \Yii::$app->user->id;
            $this->transSave($story);
        }else {
            return $this->render('publish', [
                'story' => $story,
            ]);
        }
    }

    public function actionUpdate($id)
    {
        $story = Story::findOne(['id'=>$id]);
        if ($story->load(\Yii::$app->request->post())){
            $story->updated_by = \Yii::$app->user->id;
            $this->transSave($story);
        }else {}
        return $this->render('update', [
            'story' => $story,
        ]);
    }

    /**
     * 浏览个人文章
     * @param $id
     * @return string
     */
    public function actionView($id)
    {
        $story = $this->getYouStory($id);
        return $this->render('view', [
            'story' => $story,
        ]);
    }

    public function actionDelete($id)
    {
        $this->getYouStory($id)->delete();
        return $this->redirect(['index']);
    }
}