<script setup>
import { reactive, onMounted, ref } from 'vue';
import axios from 'axios';
import { ElNotification } from 'element-plus';

defineProps({
  authItems: Array
})

const item = reactive({
  品名CD: '',
  ジャンル: [],
  タイトル: '',
  詳細: '',
  YouTube動画リンク: [''],
  files: [''],
});

const removedFiles = ref([]); // 削除されたファイルを追跡
const removedYouTubeLinks = ref([]); // 削除されたYouTubeリンクを追跡

const getKeyFromUrl = () => {
  const params = new URLSearchParams(window.location.search);
  return params.get('key');
};

const reLoadItem = () => {
  const key = getKeyFromUrl();
  if (key) {
    axios.get('/api/contents/detail', {
      params: { key: key }
    })
    .then((res) => {
      Object.assign(item, res.data.data);

      if (typeof item.ジャンル === 'string') {
        item.ジャンル = item.ジャンル.split(',');
      }

      if (typeof item.files === 'string') {
        try {
          item.files = JSON.parse(item.files);
        } catch (e) {
          item.files = [];
        }
      }

      if (typeof item.YouTube動画リンク === 'string') {
        try {
          item.YouTube動画リンク = JSON.parse(item.YouTube動画リンク);
        } catch (e) {
          item.YouTube動画リンク = [];
        }
      }

      
      if (!item.files || item.files.length === 0) {
        item.files = [''];
      }
      
      if (!item.YouTube動画リンク || item.YouTube動画リンク.length === 0) {
        item.YouTube動画リンク = [''];
      }

    })
    .catch((error) => {
      console.error('データ取得エラー:', error);
    });
  } else {
    console.error('URLにkeyパラメータがありません。');
    // keyがない場合も、デフォルトのリンク入力欄を表示
    item.YouTube動画リンク = [''];
  }
};

const edit = () => {
  const formData = new FormData();
  const genreOrder = ['動画', '資料', 'テキスト'];
  item.ジャンル.sort((a, b) => genreOrder.indexOf(a) - genreOrder.indexOf(b));

  formData.append('品名CD', item.品名CD || '');
  formData.append('ジャンル', item.ジャンル.join(',') || '');
  formData.append('タイトル', item.タイトル || '');
  formData.append('詳細', item.詳細 || '');

  // `files`が配列でかつ、空でない場合にのみフォームデータに追加
  if (Array.isArray(item.files) && item.files.length > 0) {
    item.files.forEach((file) => {
      if (file instanceof File) formData.append('files[]', file);
    });
  }

  // `YouTube動画リンク`が配列でかつ、空でない場合にのみフォームデータに追加
  if (Array.isArray(item.YouTube動画リンク) && item.YouTube動画リンク.length > 0) {
    item.YouTube動画リンク.forEach((link) => {
      if (link !== '') formData.append('YouTube動画リンク[]', link);
    });
  }

  formData.append('removedFiles', JSON.stringify(removedFiles.value)); // 削除されたファイルリストを追加
  formData.append('removedYouTubeLinks', JSON.stringify(removedYouTubeLinks.value)); // 削除されたYouTubeリンクリストを追加

  axios.post('/api/contents/edit', formData)
    .then((res) => {
      ElNotification({
        title: 'Success',
        message: '登録成功しました',
        type: 'success',
      });
      setTimeout(() => {
        window.location.href = '/contents';
      }, 1000); 
    })
    .catch((error) => {
      console.log('error ' + error);
      ElNotification({
        title: 'Error',
        message: '登録に失敗しました',
        type: 'error',
      });
    });
};


//20250825　非表示
// const removeFile = (index) => {
//   // if (item.files[index] && typeof item.files[index] === 'string') {
//   //   removedFiles.value.push(item.files[index]);
//   // }
//   removedFiles.value.push(index);
//   item.files.splice(index, 1);
// };

//20250825　修正
const removeFile = (index) => {
  removedFiles.value.push(index); // インデックスを送る
  item.files.splice(index, 1);
};

//20250825　非表示
// const removeYouTubeLink = (index) => {
//   if (item.YouTube動画リンク[index] && typeof item.YouTube動画リンク[index] === 'string') {
//     removedYouTubeLinks.value.push(item.YouTube動画リンク[index]);
//   }
//   item.YouTube動画リンク.splice(index, 1);

// };

//20250825　修正
const removeYouTubeLink = (index) => {
  removedYouTubeLinks.value.push(index);
  item.YouTube動画リンク.splice(index, 1);
};

onMounted(() => {
  reLoadItem();
  
});

const addFileInput = () => {
  item.files.push(null);
};

const updateFile = (event, index) => {
  item.files[index] = event.target.files[0];
};

const addYouTubeLinkInput = () => {
  item.YouTube動画リンク.push('');
};
</script>

<template>
  <section class="section dashboard">
    <div class="row">
      <ol class="breadcrumb">
        <!-- <li><router-link to="/home">ホーム</router-link></li> -->
        <li v-if="authItems?.[0]?.ホーム == 0">
          <router-link to="/home">ホーム</router-link>
        </li>
        <li>コンテンツ管理</li>
        <li><router-link to="/contents">編集</router-link></li>
        <li>コンテンツ情報編集</li>
      </ol>
      <div class="col-lg-12">
        <div class="card">
          <div class="contents_head">
            <h5 class="card-title">コンテンツ情報編集</h5>
          </div>
          <el-form v-if="item" :model="item">
            <div class="row space align-center justify-content-between page group contents space_d">
              
              <div class="col-lg-6">
                <label class="col-form-label">品名CD</label>
                <input type="text" class="form-control normal" v-model="item.品名CD">
              </div>
              <div class="col-lg-6 align_center">
                <label class="col-form-label">ジャンル</label>
                <div class="form-check">
                  <input class="form-check-input s" type="checkbox" v-model="item.ジャンル" value="動画">
                  <label class="form-check-label">動画</label>
                </div>
                <div class="form-check">
                  <input class="form-check-input s" type="checkbox" v-model="item.ジャンル" value="資料">
                  <label class="form-check-label">資料</label>
                </div>
                <div class="form-check">
                  <input class="form-check-input s" type="checkbox" v-model="item.ジャンル" value="テキスト">
                  <label class="form-check-label">テキスト</label>
                </div>
              </div>
              <div class="col-lg-12">
                <label class="col-form-label">タイトル</label>
                <input type="text" class="form-control normal" v-model="item.タイトル">
              </div>
              <div class="col-lg-12">
                <label class="col-form-label">詳細</label>
                <textarea class="form-control normal" style="height: 100px" v-model="item.詳細"></textarea>
              </div>
              <div class="col-lg-12">
                <label class="col-form-label">素材アップロード</label>
                <div id="input_pluralBox">
                  <div id="input_plural" v-for="(file, index) in item.files" :key="index">
                    <input type="file" class="file-button" @change="e => updateFile(e, index)">
                    <!-- <span>{{ file }}</span> -->
                     <!-- 修正　20250825 -->
                     <span v-if="typeof file === 'string'">{{ file }}</span>

                    <input type="button" value="－" class="del pluralBtn" @click="removeFile(index)">
                    <input type="button" value="＋" class="add pluralBtn" @click="addFileInput">
                  </div>
                </div>
              </div>
              <div class="col-lg-12">
                <label class="col-form-label">YouTube動画リンク</label>
                <div id="input_pluralBox_b">
                  <div id="input_plural_b" v-for="(link, index) in item.YouTube動画リンク" :key="index">
                    <input type="text" class="form-control normal yu_link" v-model="item.YouTube動画リンク[index]">
                    <input type="button" value="－" class="del pluralBtn" @click="removeYouTubeLink(index)">
                    <input type="button" value="＋" class="add pluralBtn" @click="addYouTubeLinkInput">
                  </div>
                </div>
              </div>
              <div class="col-sp-12 btn_center ma_top_a line_up center center_a">
                <a href="#" class="button_r back none od_b" onclick="window.history.back(); return false;">戻る</a>
                <button type="button" @click="edit" class="button_r none search">登録</button>
              </div>
            </div>
          </el-form>
        </div>
      </div>
    </div>
  </section>
</template>
