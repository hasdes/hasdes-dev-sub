<script setup>
import {reactive,ref} from 'vue'
import axios from 'axios';
import {ElNotification} from 'element-plus'

const isVisible=ref(false);
const form=reactive({
  name:'',
  price:null,
});
const emit = defineEmits(['reLoad']);
const create=()=>{
  axios.post('/api/fruits/create',form)
    .then((res)=>{
      ElNotification({
        title: 'Success',
        message: form.name+'を作成しました',
        type: 'success',
      })
      emit('reLoad');
      isVisible.value=false;
    }).catch((error)=>{
      ElNotification({
        title: 'Error',
        message: form.name+'の作成に失敗しました',
        type: 'error',
      })
    });
}

const open=()=>{
  isVisible.value=true;
}
defineExpose({
  open,
})
</script>

<template>
<el-dialog v-model="isVisible" title="作成">
    <el-form :model="form">
      <el-form-item label="名称" :label-width="140">
        <el-input v-model="form.name" autocomplete="off" />
      </el-form-item>
      <el-form-item label="価格" :label-width="140">
        <el-input v-model="form.price" autocomplete="off" />
      </el-form-item>
    </el-form>
    <template #footer>
      <span class="dialog-footer">
        <el-button @click="create" >作成</el-button>
        <el-button @click="isVisible = false">取消</el-button>
      </span>
    </template>
  </el-dialog> 
</template>