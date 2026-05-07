import './bootstrap';
// import 'bootstrap/dist/css/bootstrap.min.css';
import 'bootstrap-icons/font/bootstrap-icons.css';

//Vue.js のインポートと初期化
import { createApp } from 'vue';
import App from './App.vue';

// Element Plus のインポート(Vue 3 用の UI コンポーネントライブラリ)
import ElementPlus from 'element-plus'; 
import 'element-plus/dist/index.css';


const app = createApp(App);//これで Vue アプリケーションを作成し、app という変数に保存しています。

app.use(ElementPlus);

import * as vueRouter from "vue-router";
import Layout from './Layouts/Layout.vue'
import fruitsList from './Pages/Fruits/List.vue'

import Login from './Pages/Auth/Login.vue'
import ELogin from './Pages/Auth/ELogin.vue'

import Logout from './Pages/Auth/Logout.vue'
import authList from './Pages/Auth/List.vue'

import authAdd from './Pages/Auth/Add.vue'
import authEdit from './Pages/Auth/Edit.vue'
import { createPinia } from 'pinia'

//商品月間
import ItemList from './Pages/Item/List.vue'
import ItemDetail from './Pages/Item/Detail.vue'
import ItemContentsDetail from './Pages/Item/ItemContentsDetail.vue'


import StaffList from './Pages/Staff/List.vue'
//カスタマー詳細
import CustomerList from './Pages/Customer/List.vue'
import CustomerDetail from './Pages/Customer/Detail.vue'

import StockList from './Pages/Stock/List.vue'

import ContentsAdd from './Pages/Contents/Add.vue'
import ContentsList from './Pages/Contents/List.vue'
import ContentsDetail from './Pages/Contents/Detail.vue'
import ContentsEdit from './Pages/Contents/Edit.vue'

import MessageList from './Pages/Message/List.vue'
import MessageDetail from './Pages/Message/Detail.vue'

import SystemMessageList from './Pages/SystemMessage/List.vue'
import SystemMessageDetail from './Pages/SystemMessage/Detail.vue'


import HomeList from './Pages/Home/List.vue'

import LogList from './Pages/Log/List.vue'
import LogDetail from './Pages/Log/Detail.vue'

import DispatchList from './Pages/Dispatch/List.vue'//直送配車計画
import DispatchFilter from './Pages/Dispatch/Filter.vue'//拠点

import HelpDeskList from './Pages/HelpDesk/List.vue'//社内ヘルプデスク


// OCR受注変換データ
import OcrList from './Pages/Ocr/List.vue'//マスタ一覧
import OcrEdit from './Pages/Ocr/Edit.vue'//マスタ一覧

// OCR変換マスタ
import ConverList from './Pages/Conver/List.vue'//マスタ一覧
// 得意先
import ConverCustomerList from './Pages/ConverCustomer/List.vue'//得意先一覧
import ConverCustomerDetail from './Pages/ConverCustomer/Detail.vue'//変換一覧
import ConverCustomerEdit from './Pages/ConverCustomer/Edit.vue'//変換更新
// 出荷先
import ConverShippingList from './Pages/ConverShipping/List.vue'//出荷先一覧
import ConverShippingDetail from './Pages/ConverShipping/Detail.vue'//変換一覧
import ConverShippingEdit from './Pages/ConverShipping/Edit.vue'//変換更新
// 商品
import ConverProductList from './Pages/ConverProduct/List.vue'//商品一覧
import ConverProductDetail from './Pages/ConverProduct/Detail.vue'//変換一覧
import ConverProductEdit from './Pages/ConverProduct/Edit.vue'//変換更新

//誤出荷防止
import PickupList from './Pages/Pickup/List.vue'
import PickupEdit from './Pages/Pickup/Edit.vue'

//目安納期
import LeadTimeList from './Pages/LeadTime/List.vue'//マスタ一覧
import LeadTimeEdit from './Pages/LeadTime/Edit.vue'//
import LeadTimeUpdate from './Pages/LeadTime/Update.vue'//
import LeadTimeAdd from './Pages/LeadTime/Add.vue'//






import { authGuard } from "./auth-guard";


import axios from 'axios';//追加

const pinia = createPinia();  // Piniaを作成
app.use(pinia);  // Piniaをアプリに適用

//ルーティングの設定
const routes = [
    {
        path: '/',
        redirect: { name: 'home.list' }  // ルートアクセス時に/homeにリダイレクト
    },
    {
        path: '/',
        name: '/',
        component: Layout,
        children: [
            {
                path: 'fruits',
                name: 'fruits.list',
                component: fruitsList
            },
            {
                path: 'auth/logout',
                name: 'auth.logout',
                component: Logout
            },
            {
                path: 'auth/list',
                name: 'auth.list',
                component: authList
            },
            {
                path: 'auth/add',
                name: 'auth.add',
                component: authAdd
            },
            {
                path: 'auth/edit',
                name: 'auth.edit',
                component: authEdit
            },
            {
                path: 'item',
                name: 'item.list',
                component: ItemList
            },
            {
                path: 'item/detail',
                name: 'item.detail',
                component: ItemDetail
            },
            {
                path: 'item/ContentsDetail',
                name: 'item.ContentsDetail',
                component: ItemContentsDetail
            },
            {
                path: 'staff',
                name: 'staff.list',
                component: StaffList
            },
            {
                path: 'customer',
                name: 'customer.list',
                component: CustomerList
            },
            {
                path: 'customer/detail',
                name: 'customer.detail',
                component: CustomerDetail
            },
            {
                path: 'stock',
                name: 'stock.list',
                component: StockList
            },
            {
                path: 'contents/add',
                name: 'contents.add',
                component: ContentsAdd
            },
            {
                path: 'contents/detail',
                name: 'contents.detail',
                component: ContentsDetail
            },
            {
                path: 'contents/Edit',
                name: 'contents.Edit',
                component: ContentsEdit
            },
            {
                path: 'message',
                name: 'message.list',
                component: MessageList
            },
            {
                path: 'message/detail',
                name: 'message.detail',
                component: MessageDetail
            },
            {
                path: 'systemmessage',
                name: 'systemmessage.list',
                component: SystemMessageList
            },
            {
                path: 'systemmessage/detail',
                name: 'systemmessage.detail',
                component: SystemMessageDetail
            },
            {
                path: 'home',
                name: 'home.list',
                component: HomeList
            },
            {
                path: 'contents',
                name: 'contents.list',
                component: ContentsList
            },
            {
                path: 'log',
                name: 'log.list',
                component: LogList
            },
            {
                path: 'log/detail',
                name: 'log.detail',
                component: LogDetail
            },
            //直送配列
            {
                path: 'dispatch',
                name: 'dispatch.list',
                component: DispatchList,
                    meta: {
                        item: 'dispatch'
                    }
            },
            {
                path: 'dispatch/filter',
                name: 'dispatch.filter',
                component: DispatchFilter
            },
            //社内ヘルプデスク
            {
                path: 'helpdesk',
                name: 'helpdesk.list',
                component: HelpDeskList
            },

            //OCR受注入力データ変換一覧
            {
                path: 'ocr',
                name: 'ocr.list',
                component: OcrList
            },
            //OCR受注入力データ変換詳細
            {
                path: '/ocr/edit',
                name: 'ocr.edit',
                component: OcrEdit
            },

            //OCR変換マスタ
            {
                path: 'conver',
                name: 'conver.list',
                component: ConverList
            },
            //得意先変換マスタ
            {
                path: 'convercustomer',
                name: 'convercustomer.list',
                component: ConverCustomerList
            },
            //得意先変換一覧
            {
                path: 'convercustomer/Detail',
                name: 'convercustomer.Detail',
                component: ConverCustomerDetail
            },
            //得意先変換更新
            {
                path: 'convercustomer/Edit',
                name: 'convercustomer.Edit',
                component: ConverCustomerEdit
            },
            //出荷先変換マスタ
            {
                path: 'convershipping',
                name: 'convershipping.list',
                component: ConverShippingList
            },
            //出荷先変換一覧
            {
                path: 'convershipping/Detail',
                name: 'convershipping.Detail',
                component: ConverShippingDetail
            },
            //出荷先変換更新
            {
                path: 'convershipping/Edit',
                name: 'convershipping.Edit',
                component: ConverShippingEdit
            },
            //商品変換マスタ
            {
                path: 'converproduct',
                name: 'converproduct.list',
                component: ConverProductList
            },
            //商品変換一覧
            {
                path: 'converproduct/Detail',
                name: 'converproduct.Detail',
                component: ConverProductDetail
            },
            //商品変換更新
            {
                path: 'converproduct/Edit',
                name: 'converproduct.Edit',
                component: ConverProductEdit
            },
            //集荷一覧
            {
                path: 'pickup',
                name: 'pickupt.list',
                component: PickupList
            },
            //集荷詳細
            {
                path: 'pickup/Edit',
                name: 'pickup.Edit',
                component: PickupEdit
            },
            // 目安納期
            {
                path: '/leadtime',
                name: 'leadtime.list',
                component: LeadTimeList
            },
            {
                path: '/leadtime/Edit',
                name: 'leadtime.Edit',
                component: LeadTimeEdit
            },
            {
                path: '/leadtime/Update',
                name: 'leadtime.Update',
                component: LeadTimeUpdate
            },
            {
                path: '/leadtime/Add',
                name: 'leadtime.Add',
                component: LeadTimeAdd
            },




        ]
    },
    {
        path: '/login',
        name: 'auth.login',
        component: Login
    },
    {
        path: '/elogin',
        name: 'auth.elogin',
        component: ELogin
    }
];

//Vue Router の設定
const router = vueRouter.createRouter({
    history: vueRouter.createWebHistory(),
    routes,
    scrollBehavior(to, from, savedPosition) {
        if (savedPosition) {
            return savedPosition; // 前のスクロール位置を保存する場合
        } else {
            return { top: 0 }; // 常にページトップにスクロールさせる場合
        }
    },
});

// authGuard(router); 

//ページ遷移が可能に
app.use(router);

// =====================================================
// ★ 401 エラー（セッション切れ）を検知して自動ログインへ
// =====================================================
axios.interceptors.response.use(
  response => response,
  error => {
    if (error.response && error.response.status === 401) {

      // ログインページに既にいるなら何もしない
      if (router.currentRoute.value.path !== '/elogin') {
        alert('セッションの有効期限が切れました。再ログインしてください。');

        // セッションストレージ（フィルタなど）をクリア
        sessionStorage.clear();

        // 自動的にログイン画面へ遷移
        router.push('/elogin');
      }
    }

    return Promise.reject(error);
  }
);


//Vue アプリが表示され、動作するようになります。
app.mount('#app');


//対象のページへリダイレクトさせる
router.beforeEach(async (to, from, next) => {
  const publicPages = ['/elogin']; // ログイン不要なページ
  const authRequired = !publicPages.includes(to.path);

  // すでにログインページにいるなら認証チェック不要
  if (!authRequired) return next();

  // セッション認証チェック
  try {
    const res = await axios.get('/api/user', { withCredentials: true });

    if (!res.data?.担当者CD) {
      throw new Error('未ログイン');
    }

    // 認証OK → 次へ進む
    next();
  } catch (error) {
    // 未ログイン → /elogin にリダイレクト（元のページは redirect クエリで保持）
    next({
      path: '/elogin',
      query: { redirect: to.fullPath }
    });
  }
});
