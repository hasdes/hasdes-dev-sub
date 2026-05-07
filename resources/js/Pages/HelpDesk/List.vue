<script setup>
defineProps({
  authItems: Array
})
</script>

<template>
  <section class="section dashboard full-height-section">
    <ol class="breadcrumb">
      <!-- <li><router-link to="/home">ホーム</router-link></li> -->
      <li v-if="authItems?.[0]?.ホーム == 0">
        <router-link to="/home">ホーム</router-link>
      </li>
      <li>社内ヘルプデスク</li>
    </ol>

    <div class="row full-height-row">
      <div class="col-lg-12">
        <div class="card full-height-card">
          <div class="contents_head">
            <h5 class="card-title">社内ヘルプデスク　AIアシスタント「チャチャ」</h5>
          </div>
          <div class="iframe-wrapper">
            <iframe
              id="chatui"
              src="https://has-ai.happy-create.biz/chatbot_happycreate/EiIXGpdkI6k2jHRG"
              frameborder="0"
              allow="microphone"
              loading="lazy"
            ></iframe>
          </div>
        </div>
      </div>
    </div>
  </section>
</template>

<style scoped>
.full-height-section {
  height: 90vh;
  display: flex;
  flex-direction: column;
}
.full-height-row {
  flex: 1;
}
.full-height-card {
  height: 100%;
  display: flex;
  flex-direction: column;
}
.iframe-wrapper {
  flex: 1;
  display: flex;
}
.iframe-wrapper iframe {
  width: 100%;
  height: 100%;
  border: none;
}

/* タブレット横向き・小さなデスクトップ */
@media screen and (max-width: 1024px) and (max-height: 600px) {
  .iframe-wrapper iframe {
    height: 100vh;
  }
  .iframe-wrapper {
    transform: scale(0.8);
    transform-origin: top left;
    width: calc(100% / 0.8);
    height: calc(100% / 0.8);
  }
  .full-height-card {
    height: 70%;
  }
}

/* スマホ全般 */
@media screen and (max-width: 600px) {
  .full-height-card {
    height: 70%;
  }
}

/* スマホ縦向き全般 (iPhone XR〜Galaxy) */
@media screen and (max-width: 767px) and (orientation: portrait) {
  .iframe-wrapper {
    transform: scale(0.7);
    transform-origin: top left;
    width: calc(100% / 0.7);
    height: calc(100% / 0.7);
    overflow: visible;
  }
  .iframe-wrapper iframe {
    height: 120vh;
  }
}

/* スマホ横向き (幅740〜932px) */
@media screen and (max-width: 932px) and (orientation: landscape) {
  .iframe-wrapper iframe {
    height: 140vh;
  }
}

</style>