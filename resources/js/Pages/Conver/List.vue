<script setup>
import { useRouter } from 'vue-router'; // ← 追加
const router = useRouter(); // ← ここで router を取得

defineProps({
  authItems: Array
})

// 得意先 編集ページへ遷移する関数を追加
const goToCustomer = () => {
  router.push({ path: '/convercustomer' });
};

// 出荷先 編集ページへ遷移する関数を追加
const goToShipping = () => {
  router.push({ path: '/convershipping' });
};
// 商品CD 編集ページへ遷移する関数を追加
const goToProduct = () => {
  router.push({ path: '/converproduct' });
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
          <li>販売管理</li>
          <li>OCR変換マスタ</li>
        </ol>
        <div class="col-lg-12">
          <div class="card">
              <div class="contents_head">
                <h5 class="card-title">マスタ一覧</h5>
              </div>
              <div class="row space align-center justify-content-between page group">
                <div class="col-md-7">
                  <label class="col-form-label">得意先</label>   
                  <button @click.prevent="goToCustomer()" type="button" class="button_r none search cont">詳細</button>
                  <!-- <button onclick="location.href='customer.html'" type="button" class="button_r none search cont">編集</button>                   -->
                </div> 
                <div class="col-md-7">
                  <label class="col-form-label">出荷先</label>   
                    <button @click.prevent="goToShipping()" type="button" class="button_r none search cont">詳細</button>
                  <!-- <button onclick="location.href='shipping.html'" type="button" class="button_r none search cont">編集</button>                      -->
                </div>    
                <div class="col-md-7">
                  <label class="col-form-label">商品</label>   
                    <button @click.prevent="goToProduct()" type="button" class="button_r none search cont">詳細</button>
                  <!-- <button onclick="location.href='product.html'" type="button" class="button_r none search cont">編集</button>                                         -->
                </div>                                                                                             
              </div>              

          </div>
        </div>  
      </div>
    </section>
</template>