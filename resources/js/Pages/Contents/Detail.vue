<script setup>
import { onMounted, ref } from 'vue';
import axios from 'axios';

defineProps({
  authItems: Array
})

const item = ref(null); // 単一のデータを扱うため、変数名を `item` に変更

// クエリパラメータからkeyを取得する関数
const getKeyFromUrl = () => {
  const params = new URLSearchParams(window.location.search);
  return params.get('key'); // URLから 'key' パラメータを取得
};

// 顧客情報を取得する関数
const reLoadItem = () => {
  const key = getKeyFromUrl();
  if (key) {
    axios.get('/api/contents/detail', {
        params: { key: key }
      })
      .then((res) => {
        item.value = res.data.data || null;

        // filesがJSON文字列の場合、配列に変換
        if (item.value && typeof item.value.files === 'string') {
          try {
            item.value.files = JSON.parse(item.value.files);
          } catch (e) {
            console.error('ファイル情報の解析に失敗しました。', e);
            item.value.files = [];
          }
        }
        if (item.value && typeof item.value.YouTube動画リンク === 'string') {
          try {
            item.value.YouTube動画リンク = JSON.parse(item.value.YouTube動画リンク);
          } catch (e) {
            console.error('ファイル情報の解析に失敗しました。', e);
            item.value.YouTube動画リンク = [];
          }
        }
      })
      .catch((error) => {
        console.error('データ取得エラー:', error);
      });
  } else {
    console.error('URLにkeyパラメータがありません。');
  }
};

// const nl2br = (str) => {
//   if (!str) return '';
//   return str.replace(/\n/g, '<br>');
// };

//リンク化
const formatDetails = (text) => {
  if (!text) return 'データがありません';

  // 改行を <br> に変換
  const withLineBreaks = text.replace(/\n/g, '<br>');

  // URLをリンクに変換（http, https に対応）
  const linked = withLineBreaks.replace(
    /https?:\/\/[^\s<]+/g,
    (url) => `<a href="${url}" target="_blank" rel="noopener noreferrer">${url}</a>`
  );

  return linked;
};

//youtube動画リンク化
const isValidUrl = (str) => {
  try {
    const url = new URL(str);
    return url.protocol === 'http:' || url.protocol === 'https:';
  } catch (_) {
    return false;
  }
};



// onMounted内でDOM要素の存在をチェックし、イベントを追加
onMounted(() => {
  reLoadItem();

  const sidebarToggleBtn = document.getElementById('sidebar-toggle-btn');
  const sidebar = document.getElementById('sidebar');

  if (sidebarToggleBtn && sidebar) {
    sidebarToggleBtn.addEventListener('click', () => {
      sidebar.classList.toggle('active');
    });
  } else {
    console.warn('表示エラー');
  }
});

</script>

<template>
  <section v-if="item" class="section dashboard">
    <div class="row">
      <ol class="breadcrumb">
        <!-- <li><router-link to="/home">ホーム</router-link></li> -->
        <li v-if="authItems?.[0]?.ホーム == 0">
          <router-link to="/home">ホーム</router-link>
        </li>
        <li>コンテンツ管理</li>
        <li><router-link to="/contents">編集</router-link></li>
        <li>コンテンツ情報詳細</li>
      </ol>
      <div class="col-lg-12">
        <div class="card ma_btm_bm">
          <div class="contents_head">
            <h5 class="card-title">コンテンツ情報詳細</h5>
          </div>
          <div class="row space align-center justify-content-between page space_d">
            <div class="col-md-6">
              <label class="col-form-label">ジャンル</label>   
              <span>{{ item?.ジャンル || 'データがありません' }}</span>                     
            </div>    
     
            <div class="col-md-6">
              <label class="col-form-label">品名CD</label>   
              <span>{{ item?.品名CD || 'データがありません' }}</span>                     
            </div> 
            <div class="col-md-12 add">
              <label class="col-form-label">タイトル</label>   
              <p>{{ item?.タイトル || 'データがありません' }}</p>                     
            </div> 
            <div class="col-md-12 add">
              <label class="col-form-label">詳細</label>   
              <div class="kahen">
                <!-- <p v-html="nl2br(item?.詳細 || 'データがありません')"></p> -->
                <p v-html="formatDetails(item?.詳細 || 'データがありません')"></p>

                <div class="file-list">
                  <div v-for="(file, index) in item.files" :key="index" class="file-item">
                    <!-- PDFファイルの場合 -->
                    <template v-if="file.endsWith('.pdf')">
                      <a :href="`/storage/uploads/${file}`" target="_blank" class="pdf-container">
                        <img src="https://hasdes.com/img/pdf.png" alt="pdficon" class="pdf-icon">
                        <span>{{ file }}</span>
                      </a>
                    </template>
                    <!-- 画像ファイルの場合 -->
                    <template v-else-if="file.match(/\.(jpg|jpeg|png|gif)$/i)">
                      <a :href="`/storage/uploads/${file}`" target="_blank" class="image-container">
                        <img :src="`/storage/uploads/${file}`" alt="画像" class="img_dami">
                      </a>
                    </template>
                    <!-- 不明なファイル形式の場合 -->
                    <template v-else>
                      <span>不明なファイル形式: {{ file }}</span>
                    </template>
                  </div>
                </div>
              </div>
            </div> 
            <div class="col-md-6 movie divtube" v-for="(youtubed, index) in item.YouTube動画リンク" :key="index">
              <label class="col-form-label video-label">YouTube動画{{ index+1 }}</label>                    
              <div class="divtube" v-html="youtubed"></div>
            </div>

            <!-- <div class="col-md-12 add movie divtube" v-for="(youtubed, index) in item.YouTube動画リンク" :key="index">
              <label class="col-form-label">YouTube動画{{ index + 1 }}</label>
              <div class="divtube">
                <template v-if="isValidUrl(youtubed)">
                  <a :href="youtubed" target="_blank" rel="noopener noreferrer">{{ youtubed }}</a>
                </template>
                <template v-else>
                  <span class="divtube">{{ youtubed }}</span>
                </template>
              </div>
            </div>    -->

            <div class="col-sp-12 btn_center ma_top_a line_up center center_a">
              <a href="#" class="button_r back none od_b" onclick="window.history.back(); return false;">戻る</a> 
            </div>
          </div>              
        </div>
      </div>
    </div>
  </section>
  <p v-else>データがありません。</p>
</template>

<style>
.kahen {
  margin: 20px;
}

.file-list {
  display: flex;
  flex-wrap: wrap; /* 横並びにしつつ折り返し可能にする */
  gap: 10px; /* 各ファイル間のスペース */
}

.file-item {
  display: flex;
  flex-direction: column; /* PDFのアイコンとテキストを縦並びにする */
  align-items: center;
  justify-content: center;
  width: 200px; /* 必要に応じて調整 */
}

.pdf-container {
  text-align: center;
  font-size: 14px;
  word-break: break-all; /* 長いファイル名を折り返す */
}

.pdf-icon {
  width: 100px;
  height: auto;
  margin-bottom: 5px;
}

.image-container {
  display: inline-block;
  width: 200px; /* 正方形の枠 */
  height: 150px;
  background-color: #f0f0f0; /* 背景色 */
  position: relative;
  overflow: hidden;
}

.img_dami {
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  max-width: 100%;
  max-height: 100%;
  object-fit: cover;
}
.divtube{
  max-height: 300px;
}


</style>