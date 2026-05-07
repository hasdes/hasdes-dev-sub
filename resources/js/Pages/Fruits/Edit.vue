<script setup>
import {reactive,ref} from 'vue'
import axios from 'axios';
import {ElNotification} from 'element-plus'

const emit = defineEmits(['reLoad']);
const isVisible=ref(false);
const form=reactive({
  id:null,
  name:'',
  price:null,
});
const update=()=>{
  axios.put('/api/fruits/update/'+form.id,form)
    .then((res)=>{
      ElNotification({
        title: 'Success',
        message: form.name+'を更新しました',
        type: 'success',
      })
      emit('reLoad');
    }).catch((error)=>{
      ElNotification({
        title: 'Error',
        message: form.name+'の更新に失敗しました',
        type: 'error',
      })
    });
}

const open=(item)=>{
  form.id=item.id;
  form.name=item.name;
  form.price=item.price;
  isVisible.value=true;
}
defineExpose({
  open,
})
</script>

<template>
<el-dialog v-model="isVisible" title="編集">
    <el-form :model="form">
      <el-form-item label="id" :label-width="140">
        {{ form.id }}
      </el-form-item>
      <el-form-item label="名称" :label-width="140">
        <el-input v-model="form.name" autocomplete="off" />
      </el-form-item>
      <el-form-item label="価格" :label-width="140">
        <el-input v-model="form.price" autocomplete="off" />
      </el-form-item>
    </el-form>
    <template #footer>
      <span class="dialog-footer">
        <el-button @click="update" >更新</el-button>
        <el-button @click="isVisible = false">取消</el-button>
      </span>
    </template>
  </el-dialog> 
</template>