<script>
import { ref, onMounted, onBeforeUnmount, watch } from 'vue';
import { useRoute } from 'vue-router';
import axios from 'axios';

export default {
  data() {
    return {
      page: 1, // ページネーション用の値
      items: [], // アイテムリスト
      itemsTotal: 0, // アイテムの総数
      count: null, // メッセージの数を格納
      time: null,
      isMobile: window.innerWidth <= 768, // 初期表示のアイコンをPC・モバイルで切り替え
    };
  },
  methods: {

    // 社内ヘルプデスク押下
    logHelpdeskAccess() {
      axios.post('/api/log/helpdesk')
        .then(() => {
          console.log('社内ヘルプデスク押下ログ送信成功');
        })
        .catch((error) => {
          console.error('ログ送信失敗', error);
        });
    },

    reLoadItems(filter = '') {
      const params = { page: this.page };
      if (filter !== '') {
        params['filter'] = filter;
      }
      axios.get('/api/auth/list', {
        params: params
      })
      .then((res) => {
        this.items = res.data.data;
        // console.log("itemsの中身:", this.items);  // 確認
        this.itemsTotal = res.data.total;
      })
      .catch((error) => {
        console.error(error);
      });
    },
    toggleSidebar() {
      document.body.classList.toggle('toggle-sidebar');
      const sidebarToggleBtn = document.getElementById('sidebar-toggle-btn');
    if (!this.isMobile && sidebarToggleBtn) {
      if (sidebarToggleBtn.classList.contains('bi-arrow-left-short')) {
        sidebarToggleBtn.classList.remove('bi-arrow-left-short');
        sidebarToggleBtn.classList.add('bi-arrow-right-short');
      } else {
        sidebarToggleBtn.classList.remove('bi-arrow-right-short');
        sidebarToggleBtn.classList.add('bi-arrow-left-short');
      }
    }
    },
    toggleSearchBar() {
      const searchBar = document.querySelector('.search-bar');
      if (searchBar) searchBar.classList.toggle('search-bar-show');
    },
    async getlistcount() {
      try {
        const response = await axios.get('/api/auth/listcount');
        this.count = response.data.data; // メッセージ数を設定
      } catch (error) {
        if (error.response && error.response.status === 401) {
            // 認証エラーの場合、ログインページへリダイレクト
            window.location.href = '/elogin';
          } else {
            console.error("An error occurred:", error);
          }

      }
    },
    async getlistime() {
  try {
    const responsetime = await axios.get('/api/auth/listtime');
    // console.log("API Response:", responsetime.data);
    // レスポンスデータが存在するか確認
    //   if (responsetime.data && responsetime.data['更新日時']) {
    // let fullDateTime = responsetime.data['更新日時']; // 更新日時を取得

    // if (responsetime.data && responsetime.data['更新日']) {
    //   let fullDateTime = responsetime.data['更新日']; // 更新日を取得
    if (responsetime.data) {
      let fullDateTime = responsetime.data; // 更新日を取得

      // fullDateTimeがISO形式の文字列であることを前提にDateオブジェクトに変換
      const date = new Date(fullDateTime);
      if (!isNaN(date.getTime())) { // 有効な日付か確認
        // const year = date.getFullYear(); // 年を取得
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        const hours = String(date.getHours()).padStart(2, '0');
        const minutes = String(date.getMinutes()).padStart(2, '0');

        // フォーマットしてthis.timeにセット
        this.time = `${month}/${day} ${hours}:${minutes}`;
      } else {
        console.error("無効な日付形式です。");
      }
    } else {
      console.warn("更新日時が見つかりませんでした。");
      const now = new Date();
      const currentHours = now.getHours(); // 現在の時間を取得
      this.time = `${currentHours}時更新`; 
    }

  } catch (error) {
    if (error.response && error.response.status === 404) {
        // 認証エラーの場合、ログインページへリダイレクト
        window.location.href = '/elogin';
      } else {
        console.error("An error occurred:", error);
      }
  }
},
    checkDevice() {
      this.isMobile = window.innerWidth <= 768;
    }
  },
  mounted() {
    this.reLoadItems();
    this.getlistcount();
    this.getlistime();
    // ウィンドウサイズ変更の監視
    window.addEventListener('resize', this.checkDevice);

    // サイドバーと検索バーのトグルボタンにイベントリスナーを追加
    const sidebarToggleBtn = document.querySelector('.toggle-sidebar-btn');
    if (sidebarToggleBtn) {
      sidebarToggleBtn.addEventListener('click', this.toggleSidebar);
    }

    const searchBarToggle = document.querySelector('.search-bar-toggle');
    if (searchBarToggle) {
      searchBarToggle.addEventListener('click', this.toggleSearchBar);
    }
  },
  beforeUnmount() {
    // イベントリスナーを解除
    window.removeEventListener('resize', this.checkDevice);

    const sidebarToggleBtn = document.querySelector('.toggle-sidebar-btn');
    if (sidebarToggleBtn) {
      sidebarToggleBtn.removeEventListener('click', this.toggleSidebar);
    }

    const searchBarToggle = document.querySelector('.search-bar-toggle');
    if (searchBarToggle) {
      searchBarToggle.removeEventListener('click', this.toggleSearchBar);
    }
  },
  watch: {
  // ルートの変更を監視してリロード
  '$route': function() {
    this.reLoadItems();
    this.getlistcount();
    this.getlistime();
    
    if (this.isMobile) {
      // モバイルの場合はサイドバーを閉じる
      this.sidebarOpen = false; // 状態を更新
      document.body.classList.remove('toggle-sidebar'); // サイドバーを閉じるためのクラスを削除
    } 
  }
}
};
</script>

<template>
  <!-- ======= Header ======= -->
  <header id="header" class="header fixed-top d-flex align-items-center">
    <div class="d-flex align-items-center justify-content-between">
      <router-link to="/home" class="logo d-flex align-items-center" active-class="active">
        <img src="https://hasdes.com/img/logo_w.png" alt="HASDES">
      </router-link>
      <i :class="isMobile ? 'bi bi-list toggle-sidebar-btn' : 'bi bi-arrow-left-short toggle-sidebar-btn'" id="sidebar-toggle-btn"></i>
      
    </div>
    <nav class="header-nav ms-auto">
      <ul class="d-flex align-items-center">
        <li class="nav-item">
          <router-link class="nav-link nav-icon" to="/message" active-class="active" v-if="items[0]?.メッセージ送受信 !== 1">
            <i class="fa-regular fa-envelope"></i>
            <span v-if="count >= 1" class="badge badge-number number">{{ count }}</span>
          </router-link>
        </li>
        <li class="nav-item dropdown pe-3">
          <router-link class="nav-link nav-profile d-flex align-items-center pe-0" to="/auth/list" active-class="active">
            <span class="d-none d-md-block dropdown-toggle ps-2">{{ items[0]?.担当者名 || 'データがありません' }}</span>
          </router-link>
        </li>
      </ul>
    </nav>
    <nav class="header-nav">
      <ul class="d-flex align-items-center">
        <li class="nav-item dropdown pe-3">
          <p style="color: #fff;font-size: 12px;text-align: center;">更新時間<br>{{ time }}</p>
        </li>
      </ul>
    </nav>
  </header>
  
  <aside id="sidebar" class="sidebar">
    <div class="nav-item sp_name">
      <router-link class="nav-link" to="/auth/list" active-class="active">
        <span>{{ items[0]?.担当者名 || 'データがありません' }}</span>
      </router-link>
    </div>
    <div class="sidebar-nav" id="sidebar-nav">
      <div class="main">
        <router-link to="/home" active-class="active" v-if="items[0]?.ホーム !== 1">
          <i class="fa-solid fa-house r_icon"></i>
          <span>ホーム</span>
        </router-link>
      </div>
      <div class="main">
        <router-link to="/message" active-class="active" v-if="items[0]?.メッセージ送受信 !== 1">
          <i class="fa-solid fa-paper-plane r_icon"></i>
          <span>メッセージ送受信</span>
        </router-link>
      </div>
      <details id="menu-info" v-if="items[0]?.在庫表示 !== 1 || items[0]?.商品表示 !== 1">
        <summary>
          <i class="fa-solid fa-file-lines r_icon"></i>
          <span>情報表示</span>
          <i class="bi bi-chevron-down ms-auto toggle-icon"></i>
        </summary>
        <div class="sub_menu">
          <router-link to="/stock" active-class="active" v-if="items[0]?.在庫表示 !== 1">在庫表示</router-link>
          <router-link to="/item" active-class="active" v-if="items[0]?.商品表示 !== 1">商品表示</router-link>
          <router-link to="/dispatch" active-class="active" v-if="items[0]?.直送配車計画 !== 1">直送配車計画</router-link>
        </div>
      </details>
      <details id="ocr-info"  v-if="items[0]?.販売管理 !== 1 || items[0]?.販売管理 !== 1">
        <summary>
          <i class="fa-solid fa-cart-shopping r_icon"></i>
          <span>販売管理</span>
          <i class="bi bi-chevron-down ms-auto toggle-icon"></i>
        </summary>
        <div class="sub_menu">
          <router-link to="/ocr" active-class="active" v-if="items[0]?.販売管理 !== 1">受注入力_OCR</router-link>
          <router-link to="/conver" active-class="active" v-if="items[0]?.販売管理 !== 1">OCR変換マスタ</router-link>
        </div>
      </details>
      <details id="pickup-info" v-if="items[0]?.集荷処理 !== 1">
        <summary>
          <i class="fa-solid fa-truck-moving r_icon"></i>
          <span>集荷処理</span>
          <i class="bi bi-chevron-down ms-auto toggle-icon"></i>
        </summary>
        <div class="sub_menu">
          <router-link to="/pickup" active-class="active">集荷データ登録・修正・確認</router-link>
        </div>
      </details>
      <details id="contents-info" v-if="items[0]?.新規登録 !== 1 || items[0]?.編集 !== 1">
        <summary>
          <i class="fa-solid fa-folder r_icon"></i>
          <span>コンテンツ管理</span>
          <i class="bi bi-chevron-down ms-auto toggle-icon"></i>
        </summary>
        <div class="sub_menu">
          <router-link to="/contents/add" active-class="active"v-if="items[0]?.新規登録 !== 1">新規登録</router-link>
          <router-link to="/contents" active-class="active"v-if="items[0]?.編集 !== 1">編集</router-link>
        </div>
      </details>
      <div class="main">
        <router-link to="/helpdesk" active-class="active" @click="logHelpdeskAccess" v-if="items[0]?.社内ヘルプデスク !== 1">          
          <i class="fa-solid fa-circle-question r_icon"></i>
          <span>社内ヘルプデスク</span>
        </router-link>
      </div>
      <details id="menu-system" v-if="items[0]?.従業員マスタメンテ !== 1 || items[0]?.顧客企業マスタメンテ !== 1 || items[0]?.メッセージ管理 !== 1 || items[0]?.ログ管理 !== 1">
        <summary>
          <i class="fa-brands fa-windows r_icon"></i>
          <span>システム管理</span>
          <i class="bi bi-chevron-down ms-auto toggle-icon"></i>
        </summary>
        <div class="sub_menu">
          <router-link to="/staff" active-class="active"v-if="items[0]?.従業員マスタメンテ !== 1">従業員マスタメンテ</router-link>
          <router-link to="/customer" active-class="active"v-if="items[0]?.顧客企業マスタメンテ !== 1">顧客企業マスタメンテ</router-link>
          <router-link to="/systemmessage" active-class="active"v-if="items[0]?.メッセージ管理 !== 1">メッセージ管理</router-link>
          <router-link to="/log" active-class="active"v-if="items[0]?.ログ管理 !== 1">ログ管理</router-link>
        </div>
      </details>
      <div class="main">
        <router-link to="/auth/list" active-class="active"v-if="items[0]?.ユーザー設定 !== 1">
          <i class="fa-solid fa-user r_icon"></i>
          <span>ユーザー設定</span>
        </router-link>
      </div>
      <div class="main">
        <router-link to="/auth/logout" active-class="active">
          <i class="fa-solid fa-right-from-bracket r_icon"></i>
          <span>ログアウト</span>
        </router-link>
      </div>
    </div>
  </aside>

  <main class="main" id="main">
    <!-- <router-view></router-view> -->

     <!-- 修正(親コンポーネント(Layout/Layout.vue)から子コンポーネント(Dispatch/List.vue)へデータを渡す) -->
    <router-view v-slot="{ Component }">
      <!-- <component :is="Component" :items="items" /> -->
      <component :is="Component" :authItems="items" />
    </router-view>
  </main>

</template>
