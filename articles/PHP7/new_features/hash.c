#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <time.h>

#define HASH_TABLE_MAX_SIZE 10
typedef struct HashNode_Struct HashNode;
struct HashNode_Struct
{
    char *sKey;
    int nValue;
    HashNode *pNext;
};

HashNode *hashTable[HASH_TABLE_MAX_SIZE]; //hash table data strcutrue
int hash_table_size;  //the number of key-value pairs in the hash table!

//initialize hash table
void hash_table_init()
{
    hash_table_size = 0;
    memset(hashTable, 0, sizeof(HashNode *) * HASH_TABLE_MAX_SIZE);
}


//string hash function
unsigned int hash_table_hash_str(const char *skey)
{
    const signed char *p = (const signed char *)skey;
    unsigned int h = *p;
    if(h)
    {
        for(p += 1; *p != '\0'; ++p)
            h = (h << 5) - h + *p;
    }
    return h;
}

//insert key-value into hash table
void hash_table_insert(const char *skey, int nvalue)
{
    if(hash_table_size >= HASH_TABLE_MAX_SIZE)
    {
        printf("out of hash table memory!\n");
        return;
    }

    unsigned int pos = hash_table_hash_str(skey) % HASH_TABLE_MAX_SIZE;

    HashNode *pHead =  hashTable[pos];
    while(pHead)
    {
        if(strcmp(pHead->sKey, skey) == 0)
        {
            printf("%s already exists!\n", skey);
            return ;
        }
        pHead = pHead->pNext;
    }

	// 指针生成
    HashNode *pNewNode = (HashNode *)malloc(sizeof(HashNode));
    memset(pNewNode, 0, sizeof(HashNode));
    pNewNode->sKey = (char *)malloc(sizeof(char) * (strlen(skey) + 1));
    strcpy(pNewNode->sKey, skey);
    pNewNode->nValue = nvalue;
	pNewNode->pNext = NULL;
	if (pHead == NULL) {
    	pNewNode->pNext = hashTable[pos];
    }
    
    hashTable[pos] = pNewNode;
    hash_table_size++;
}


//lookup a key in the hash table
HashNode* hash_table_lookup(const char* skey)
{
    unsigned int pos = hash_table_hash_str(skey) % HASH_TABLE_MAX_SIZE;
    if(hashTable[pos])
    {
        HashNode *pHead = hashTable[pos];
        while(pHead)
        {
            if(strcmp(skey, pHead->sKey) == 0)
                return pHead;
            pHead = pHead->pNext;
        }
    }
    return NULL;
}

//print the content in the hash table
void hash_table_print()
{
    printf("===========content of hash table=================\n");
    int i;
    for(i = 0; i < HASH_TABLE_MAX_SIZE; ++i)
        if(hashTable[i])
        {
            HashNode* pHead = hashTable[i];
            printf("%d=>", i);
            while(pHead)
            {
                printf("%s:%d  ", pHead->sKey, pHead->nValue);
                pHead = pHead->pNext;
            }
            printf("\n");
        }
}


//主程序
int main(int argc, char** argv)
{
    hash_table_init();
    printf("insert testing.........\n");
    const char *key1 = "aaammd";
    const char *key2 = "xzzyym";
    //const char *key3 = "cdcded";

    hash_table_insert(key1, 110);
    hash_table_insert(key2, 220);
    //hash_table_insert(key3, 330);

    hash_table_print();

    printf("\nlookup testing..........\n");
    HashNode* pNode = hash_table_lookup(key1);
    printf("lookup result:%d\n", pNode->nValue);
    pNode = hash_table_lookup(key2);
    printf("lookup result:%d\n", pNode->nValue);

    return 0;
}


